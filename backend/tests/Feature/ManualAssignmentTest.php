<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsBakery;
use Tests\TestCase;

/**
 * Assigning by hand on the schedule page: a line-independent task given from the grid's
 * cross-line row, which offers the first line's hours, still runs on the shift its holder
 * works - as generate places it; a second lead can be given the open station on their
 * shift, which generate never does; and each candidate shows their hours this week.
 */
class ManualAssignmentTest extends TestCase
{
    use BuildsBakery;
    use RefreshDatabase;

    private User $admin;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildBakery();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->schedule = Schedule::create(['week_start_date' => '2027-01-03', 'status' => Schedule::STATUS_DRAFT, 'created_by' => $this->admin->id]);
    }

    public function test_a_line_independent_task_takes_its_holders_shift(): void
    {
        $this->leadShift('2027-01-09');

        $this->actingAs($this->admin)->postJson('/api/v1/shift-assignments', [
            'schedule_id' => $this->schedule->id,
            'employee_id' => $this->people['Erdal']->id,
            'line_id' => $this->lines['l1']->id,
            'shift_pattern_id' => $this->dayPattern($this->lines['l1'])->id,
            'work_date' => '2027-01-09',
            'role_id' => $this->roles['silo']->id,
        ])->assertSuccessful()->assertJsonPath('rule_results', fn (array $results) => collect($results)->every(fn (array $r) => $r['status'] !== 'violation'));

        $this->assertOnLineTwosDay(ShiftAssignment::query()->where('role_id', $this->roles['silo']->id)->sole());
    }

    public function test_a_line_independent_task_moved_to_another_day_takes_that_days_shift(): void
    {
        $this->leadShift('2027-01-08');
        $this->leadShift('2027-01-09');
        $silo = ShiftAssignment::create([
            'schedule_id' => $this->schedule->id,
            'employee_id' => $this->people['Erdal']->id,
            'line_id' => $this->lines['l2']->id,
            'shift_pattern_id' => $this->dayPattern($this->lines['l2'])->id,
            'work_date' => '2027-01-08',
            'role_id' => $this->roles['silo']->id,
            'status' => ShiftAssignment::STATUS_PROPOSED,
            'source' => ShiftAssignment::SOURCE_MANUAL,
        ]);

        $this->actingAs($this->admin)->patchJson("/api/v1/shift-assignments/{$silo->id}/move", [
            'line_id' => $this->lines['l1']->id,
            'shift_pattern_id' => $this->dayPattern($this->lines['l1'])->id,
            'work_date' => '2027-01-09',
        ])->assertSuccessful();

        $this->assertOnLineTwosDay($silo->refresh());
        $this->assertSame('2027-01-09', $silo->work_date->format('Y-m-d'));
    }

    public function test_a_second_lead_can_be_given_the_open_station_on_their_shift(): void
    {
        // Marc leads Line 1's day shift five days, 40 hours; on Thursday dough is open there.
        $marc = $this->people['Marc'];
        $marc->schedulingRoles()->attach($this->roles['dough']->id);
        $day = $this->dayPattern($this->lines['l1']);
        foreach (['2027-01-03', '2027-01-04', '2027-01-05', '2027-01-06', '2027-01-07'] as $date) {
            ShiftAssignment::create(['schedule_id' => $this->schedule->id, 'employee_id' => $marc->id, 'line_id' => $this->lines['l1']->id, 'shift_pattern_id' => $day->id, 'work_date' => $date, 'role_id' => $this->roles['lead']->id, 'status' => ShiftAssignment::STATUS_PROPOSED, 'source' => ShiftAssignment::SOURCE_AUTO_SUGGESTED]);
        }
        $slot = ['line_id' => $this->lines['l1']->id, 'shift_pattern_id' => $day->id, 'work_date' => '2027-01-07', 'role_id' => $this->roles['dough']->id];

        $candidates = $this->actingAs($this->admin)->getJson("/api/v1/schedules/{$this->schedule->id}/alternative-candidates?".http_build_query([...$slot, 'ranking_mode' => 'fair']))->assertOk()->json('data');
        $offered = collect($candidates)->firstWhere('employee.id', $marc->id);
        $this->assertSame([true, true, null], [$offered['qualified'], $offered['rule_compliant'], $offered['exclusion_reason']], 'the station on the shift they already work adds no hours');

        $this->actingAs($this->admin)->postJson('/api/v1/shift-assignments', ['schedule_id' => $this->schedule->id, 'employee_id' => $marc->id, ...$slot])
            ->assertSuccessful()->assertJsonPath('rule_results', fn (array $results) => collect($results)->every(fn (array $r) => $r['status'] !== 'violation'));
    }

    public function test_candidates_show_their_hours_this_week_against_their_contract(): void
    {
        // Two lead shifts, and Silo on the first of them: 16 hours, the shared shift counted once.
        $this->leadShift('2027-01-08');
        $this->leadShift('2027-01-09');
        ShiftAssignment::create(['schedule_id' => $this->schedule->id, 'employee_id' => $this->people['Erdal']->id, 'line_id' => $this->lines['l2']->id, 'shift_pattern_id' => $this->dayPattern($this->lines['l2'])->id, 'work_date' => '2027-01-08', 'role_id' => $this->roles['silo']->id, 'status' => ShiftAssignment::STATUS_PROPOSED, 'source' => ShiftAssignment::SOURCE_MANUAL]);

        $candidates = collect($this->actingAs($this->admin)->getJson("/api/v1/schedules/{$this->schedule->id}/alternative-candidates?".http_build_query([
            'line_id' => $this->lines['l1']->id,
            'shift_pattern_id' => $this->dayPattern($this->lines['l1'])->id,
            'work_date' => '2027-01-05',
            'role_id' => $this->roles['silo']->id,
            'ranking_mode' => 'fair',
        ]))->assertOk()->json('data'))->keyBy('employee.first_name');

        $this->assertEquals([16, ['min' => 40, 'max' => 40]], [$candidates['Erdal']['week_hours'], $candidates['Erdal']['contract_hours']]);
        $this->assertEquals([0, ['min' => 16, 'max' => 16]], [$candidates['Heinrick']['week_hours'], $candidates['Heinrick']['contract_hours']]);
    }

    /**
     * Erdal leading Line 2's day shift (10:00-18:00) on this date.
     */
    private function leadShift(string $workDate): void
    {
        ShiftAssignment::create([
            'schedule_id' => $this->schedule->id,
            'employee_id' => $this->people['Erdal']->id,
            'line_id' => $this->lines['l2']->id,
            'shift_pattern_id' => $this->dayPattern($this->lines['l2'])->id,
            'work_date' => $workDate,
            'role_id' => $this->roles['lead']->id,
            'status' => ShiftAssignment::STATUS_PROPOSED,
            'source' => ShiftAssignment::SOURCE_AUTO_SUGGESTED,
        ]);
    }

    private function dayPattern(Line $line): ShiftPattern
    {
        return ShiftPattern::query()->where('shift_pattern_group_id', $line->shift_pattern_group_id)->where('slot_type', 'day')->sole();
    }

    private function assertOnLineTwosDay(ShiftAssignment $task): void
    {
        $this->assertSame(
            [$this->lines['l2']->id, $this->dayPattern($this->lines['l2'])->id],
            [$task->line_id, $task->shift_pattern_id],
            "on the lead's own 10:00-18:00, not Line 1's 08:00-16:00",
        );
    }
}
