<?php

namespace Tests\Feature;

use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Scheduling\DTOs\RosterWeekPlanData;
use App\Modules\Scheduling\Exceptions\RosterPlannerUnavailableException;
use App\Modules\Scheduling\Services\RosterPlanner;
use App\Modules\Scheduling\Services\RosterPlannerClient;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmployeeRoleExclusion;
use App\Modules\Staff\Models\EmployeeUnavailability;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The Laravel side of the roster planner (ProjectPlan.md §28): the problem built from
 * loaded models, the reading of the planner's answer, and the HTTP fallbacks. No database -
 * the planner's own test suite covers the solving.
 */
class RosterPlannerTest extends TestCase
{
    public function test_builds_the_problem_for_leads_and_operators(): void
    {
        $erdal = $this->person(7, 'Erdal', term: ['min_weekly_hours' => 40, 'max_weekly_hours' => 40]);
        $erdal->setRelation('unavailabilities', collect([
            new EmployeeUnavailability(['day_of_week' => 1, 'slot_type' => 'afternoon']),
            new EmployeeUnavailability(['day_of_week' => 2, 'slot_type' => 'night']),
        ]));
        $erdal->setRelation('leaveRequests', collect([
            new LeaveRequest(['status' => 'approved', 'start_date' => '2027-01-07', 'end_date' => '2027-01-20']),
        ]));
        $sander = $this->person(8, 'Sander');
        $ali = $this->person(80, 'Ali', term: ['min_weekly_hours' => 40, 'max_weekly_hours' => 40]);
        $ali->forceFill(['pay_type' => 'hourly', 'hourly_rate' => 22]);
        $ali->setRelation('schedulingRoles', collect([$this->role(16), $this->role(18), $this->role(99)]));
        $ali->setRelation('roleExclusions', collect([new EmployeeRoleExclusion(['scheduling_role_id' => 18])]));

        $problem = app(RosterPlanner::class)->buildProblem(
            Carbon::parse('2027-01-03'),
            1,
            ['day', 'afternoon'],
            collect([
                ['date' => '2027-01-03', 'slot_type' => 'day', 'start' => Carbon::parse('2027-01-03 08:00'), 'end' => Carbon::parse('2027-01-03 16:00')],
            ]),
            1,
            collect([
                ['date' => '2027-01-03', 'slot_type' => 'afternoon', 'line_id' => 6, 'role_id' => 16, 'start' => Carbon::parse('2027-01-03 18:00'), 'end' => Carbon::parse('2027-01-04 02:00'), 'min' => 1, 'max' => 2, 'paid_hours' => 9.5],
                ['date' => '2027-01-03', 'slot_type' => 'day', 'line_id' => 1, 'role_id' => 18, 'start' => Carbon::parse('2027-01-03 08:00'), 'end' => Carbon::parse('2027-01-03 16:00'), 'min' => 1, 'max' => 1],
            ]),
            collect([$erdal, $sander]),
            collect([$ali]),
            collect([7 => ['hours' => 80, 'counts' => ['sunday' => 2]]]),
            collect([7 => 'night', 8 => 'day']),
            collect([8 => collect([[Carbon::parse('2027-01-02 16:00'), Carbon::parse('2027-01-03 00:00')]])]),
            $this->settings(),
            'cost',
        );

        $this->assertSame(['day', 'afternoon'], $problem['shift_types']);
        $this->assertSame('cost', $problem['rules']['mode']);
        $this->assertSame(['day', 'afternoon'], $problem['rotation']);
        $this->assertSame(['sunday', 'saturday'], array_column($problem['fairness']['dimensions'], 'key'), 'night is out of scope');
        $this->assertSame([3, 3], array_column($problem['fairness']['dimensions'], 'weight'));
        $this->assertSame([3, 'fair'], [$problem['fairness']['rotation_break_weight'], $problem['rules']['weekend_policy']]);
        $this->assertSame([
            ['id' => 'lead|2027-01-03|day', 'date' => '2027-01-03', 'type' => 'day', 'role' => 'shift_lead', 'start' => '2027-01-03T08:00', 'end' => '2027-01-03T16:00', 'min' => 1, 'max' => 2, 'paid_hours' => null, 'spare_for' => ['station|2027-01-03|day|1|18']],
            ['id' => 'station|2027-01-03|afternoon|6|16', 'date' => '2027-01-03', 'type' => 'afternoon', 'role' => 'station:16', 'start' => '2027-01-03T18:00', 'end' => '2027-01-04T02:00', 'min' => 1, 'max' => 2, 'paid_hours' => 9.5],
            ['id' => 'station|2027-01-03|day|1|18', 'date' => '2027-01-03', 'type' => 'day', 'role' => 'station:18', 'start' => '2027-01-03T08:00', 'end' => '2027-01-03T16:00', 'min' => 1, 'max' => 1, 'paid_hours' => null],
        ], $problem['shifts']);

        [$first, $second, $operator] = $problem['employees'];
        $this->assertSame(['leads', ['shift_lead']], [$first['group'], $first['roles']]);
        $this->assertSame(['operators', ['station:16']], [$operator['group'], $operator['roles']], 'excluded and out-of-scope stations dropped');
        $this->assertSame([22.0, 0.0], [$operator['hourly_cost'], $first['hourly_cost']]);
        $this->assertSame([40.0, 40.0], [$first['min_hours'], $first['max_hours']]);
        $this->assertSame([0.0, 40.0], [$second['min_hours'], $second['max_hours']], 'no contract: no minimum, the default maximum');
        $this->assertSame([['weekday' => 1, 'type' => 'afternoon']], $first['unavailable'], 'night unavailability is out of scope');
        $this->assertSame(['2027-01-07', '2027-01-08', '2027-01-09'], array_column($first['absences'], 'date'), 'leave clipped to the horizon');
        $this->assertNull($first['previous_type'], 'last week\'s type is out of scope');
        $this->assertSame('day', $second['previous_type']);
        $this->assertSame([['start' => '2027-01-02T16:00', 'end' => '2027-01-03T00:00']], $second['previous_shifts']);
        $this->assertSame(['hours' => 80, 'counts' => ['sunday' => 2]], $first['history']);
        $this->assertStringContainsString('"history":{"hours":0,"counts":{}}', json_encode($problem), 'empty counts must stay a JSON object');
    }

    public function test_planner_settings_shape_the_problem(): void
    {
        $problem = app(RosterPlanner::class)->buildProblem(
            Carbon::parse('2027-01-03'),
            1,
            ['night', 'day', 'afternoon'],
            collect([['date' => '2027-01-03', 'slot_type' => 'night', 'start' => Carbon::parse('2027-01-03 00:00'), 'end' => Carbon::parse('2027-01-03 08:00')]]),
            1,
            collect(),
            collect([$this->person(7, 'Erdal')]),
            collect(),
            collect(),
            collect(),
            collect(),
            $this->settings([
                'planner_weekend_policy' => CompanySetting::PLANNER_WEEKEND_SINGLE_STAFFED,
                'planner_night_weight' => 2,
                'planner_sunday_weight' => 5,
                'planner_saturday_weight' => 0,
                'planner_rotation_break_weight' => 1,
                'planner_rotation_enabled' => false,
                'planner_max_leads_per_shift' => 3,
            ]),
        );

        $this->assertSame([], $problem['rotation'], 'rotation switched off');
        $this->assertSame('single_staffed', $problem['rules']['weekend_policy']);
        $this->assertSame('fair', $problem['rules']['mode'], 'fair unless cost is asked for');
        $this->assertSame(['night' => 2, 'sunday' => 5, 'saturday' => 0], array_column($problem['fairness']['dimensions'], 'weight', 'key'));
        $this->assertSame(1, $problem['fairness']['rotation_break_weight']);
        $this->assertSame([1, 3], [$problem['shifts'][0]['min'], $problem['shifts'][0]['max']], 'up to three leads a shift');
    }

    public function test_operators_get_a_penalty_off_their_preferred_station_and_line(): void
    {
        $ali = $this->person(80, 'Ali');
        $ali->setRelation('schedulingRoles', collect([$this->role(16, 1, preferred: true), $this->role(17, 1), $this->role(39, 6), $this->role(40)]));
        $david = $this->person(67, 'David');
        $david->setRelation('schedulingRoles', collect([$this->role(16, 1), $this->role(39, 6)]));
        $slot = fn (int $lineId, int $roleId) => ['date' => '2027-01-03', 'slot_type' => 'day', 'line_id' => $lineId, 'role_id' => $roleId, 'start' => Carbon::parse('2027-01-03 08:00'), 'end' => Carbon::parse('2027-01-03 16:00'), 'min' => 1, 'max' => 1];

        $problem = app(RosterPlanner::class)->buildProblem(
            Carbon::parse('2027-01-03'),
            1,
            ['day'],
            collect([['date' => '2027-01-03', 'slot_type' => 'day', 'start' => Carbon::parse('2027-01-03 08:00'), 'end' => Carbon::parse('2027-01-03 16:00')]]),
            1,
            collect([$slot(1, 16), $slot(1, 17), $slot(6, 39), $slot(0, 40)]),
            collect([$this->person(7, 'Erdal')]),
            collect([$ali, $david]),
            collect(),
            collect(),
            collect(),
            $this->settings(),
        );

        [$lead, $preferring, $indifferent] = $problem['employees'];
        $this->assertSame(['station:17' => 1, 'station:39' => 3, 'station:40' => 3], $preferring['preference_penalties'], 'another station of the line, then another line or none');
        $this->assertSame([], $indifferent['preference_penalties'], 'no preferred station, no penalty anywhere');
        $this->assertSame([], $lead['preference_penalties']);
    }

    public function test_keeps_the_first_week_of_the_answer(): void
    {
        $plan = RosterWeekPlanData::fromPlannerResponse([
            'status' => 'ok',
            'summary' => ['coverage_missing' => 0],
            'totals' => [['employee_id' => 7], ['employee_id' => 8], ['employee_id' => 80], ['employee_id' => 81]],
            'weeks' => [
                ['start' => '2027-01-03', 'employees' => [
                    ['employee_id' => 7, 'types' => ['day', 'afternoon'], 'shifts' => [
                        ['shift_id' => 'lead|2027-01-03|day', 'date' => '2027-01-03', 'type' => 'day'],
                        ['shift_id' => 'lead|2027-01-04|day', 'date' => '2027-01-04', 'type' => 'day'],
                        ['shift_id' => 'lead|2027-01-05|afternoon', 'date' => '2027-01-05', 'type' => 'afternoon'],
                    ]],
                    ['employee_id' => 8, 'types' => ['day'], 'shifts' => [
                        ['shift_id' => 'lead|2027-01-03|day', 'date' => '2027-01-03', 'type' => 'day'],
                    ]],
                    ['employee_id' => 80, 'types' => ['night'], 'shifts' => [
                        ['shift_id' => 'station|2027-01-03|night|6|39', 'date' => '2027-01-03', 'type' => 'night'],
                    ]],
                ]],
                ['start' => '2027-01-10', 'employees' => [
                    ['employee_id' => 7, 'types' => ['night'], 'shifts' => [
                        ['shift_id' => 'lead|2027-01-10|night', 'date' => '2027-01-10', 'type' => 'night'],
                    ]],
                ]],
            ],
        ]);

        $this->assertSame([7, 8], $plan->leadsFor('2027-01-03', 'day')->all());
        $this->assertSame([7], $plan->leadsFor('2027-01-05', 'afternoon')->all());
        $this->assertSame([80], $plan->operatorsFor('2027-01-03', 'night', 6, 39)->all());
        $this->assertTrue($plan->operatorsFor('2027-01-03', 'day', 6, 39)->isEmpty());
        $this->assertTrue($plan->leadsFor('2027-01-10', 'night')->isEmpty(), 'later weeks are only lookahead');
        $this->assertSame([7 => 'day', 8 => 'day', 80 => 'night'], $plan->locks->all(), 'a mixed week locks to the most-worked type');
        $this->assertSame([7, 8, 80, 81], $plan->plannedEmployeeIds->all(), 'planned includes people with no shift this week');
    }

    public function test_client_returns_the_plan(): void
    {
        config(['planner.url' => 'http://planner.test']);
        Http::fake(['planner.test/plan' => Http::response(['status' => 'ok', 'summary' => [], 'weeks' => []])]);

        $this->assertSame('ok', app(RosterPlannerClient::class)->plan(['weeks' => 1])['status']);
        Http::assertSent(fn ($request) => $request->url() === 'http://planner.test/plan' && $request['weeks'] === 1);
    }

    public function test_client_throws_with_the_reason_when_the_planner_rejects_or_is_down(): void
    {
        config(['planner.url' => 'http://planner.test']);
        Log::spy();

        Http::fake(['planner.test/plan' => Http::response(['status' => 'error', 'message' => 'shifts[0].type is not in shift_types'], 422)]);
        $this->assertThrows(fn () => app(RosterPlannerClient::class)->plan([]), RosterPlannerUnavailableException::class, 'shifts[0].type is not in shift_types');

        Http::fake(['planner.test/plan' => Http::failedConnection()]);
        $this->assertThrows(fn () => app(RosterPlannerClient::class)->plan([]), RosterPlannerUnavailableException::class, 'could not be reached');

        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_client_is_off_without_a_url(): void
    {
        config(['planner.url' => '']);
        Http::fake();

        $this->assertFalse(app(RosterPlannerClient::class)->isEnabled());
        $this->assertThrows(fn () => app(RosterPlannerClient::class)->plan([]), RosterPlannerUnavailableException::class, 'not configured');
        Http::assertNothingSent();
    }

    /**
     * @param  ?array<string, int>  $term
     */
    private function person(int $id, string $name, ?array $term = null): Employee
    {
        $employee = new Employee(['first_name' => $name, 'last_name' => '']);
        $employee->id = $id;
        $employee->setRelation('currentEmploymentTerm', $term === null ? null : new EmploymentTerm($term));
        $employee->setRelation('unavailabilities', collect());
        $employee->setRelation('leaveRequests', collect());
        $employee->setRelation('schedulingRoles', collect());
        $employee->setRelation('roleExclusions', collect());

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function settings(array $overrides = []): CompanySetting
    {
        return new CompanySetting([
            'planner_weekend_policy' => CompanySetting::PLANNER_WEEKEND_FAIR,
            'planner_night_weight' => 1,
            'planner_sunday_weight' => 3,
            'planner_saturday_weight' => 3,
            'planner_rotation_break_weight' => 3,
            'planner_rotation_enabled' => true,
            'planner_max_leads_per_shift' => 2,
            'planner_horizon_weeks' => 8,
            ...$overrides,
        ]);
    }

    private function role(int $id, ?int $lineId = null, bool $preferred = false): SchedulingRole
    {
        $role = new SchedulingRole(['line_id' => $lineId]);
        $role->id = $id;
        $role->setRelation('pivot', new Pivot(['is_preferred' => $preferred]));

        return $role;
    }
}
