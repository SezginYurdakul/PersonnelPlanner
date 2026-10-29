<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Scheduling\Exceptions\RosterPlannerUnavailableException;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\Services\MandatoryCoverageChecker;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\BuildsBakery;
use Tests\TestCase;

/**
 * Generate end to end on a real database (ProjectPlan.md §27.1, §28.9): the roster
 * planner's answer placed as planned, the real planner service when it's reachable (every
 * result checked against the hard rules), and a clear refusal when the planner can't answer.
 */
class GenerateScheduleTest extends TestCase
{
    use BuildsBakery;
    use RefreshDatabase;

    private const WEEK = '2027-01-03';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildBakery();
    }

    public function test_places_the_planners_answer_exactly(): void
    {
        config(['planner.url' => 'http://planner.test']);
        $answer = null;
        Http::fake(['planner.test/plan' => function (Request $request) use (&$answer) {
            $answer = $this->simplePlan($request->data());

            return Http::response($answer);
        }]);

        $schedule = $this->generateWeek(self::WEEK);

        $planned = collect($answer['weeks'][0]['employees'])
            ->flatMap(fn (array $row) => collect($row['shifts'])->map(fn (array $s) => $row['employee_id'].'|'.$this->slotKey($s['shift_id'])))
            ->sort()->values()->all();
        $placed = ShiftAssignment::query()->where('schedule_id', $schedule->id)->with(['role', 'shiftPattern'])->get()
            ->reject(fn (ShiftAssignment $a) => $a->role->id === $this->roles['silo']->id)
            ->map(fn (ShiftAssignment $a) => $a->employee_id.'|'.$a->work_date->format('Y-m-d').'|'.$a->shiftPattern->slot_type.'|'.($a->role->isStation() ? $a->role_id : 'lead'))
            ->sort()->values()->all();
        $this->assertSame($planned, $placed, 'every planned lead shift and station, and nothing else');

        $openInAnswer = 57 + 19 - count($planned);
        $this->assertSame($openInAnswer, $this->blockingGaps($schedule), 'whatever the planner left open is reported');
        $this->assertTrue(ShiftAssignment::query()->where('schedule_id', $schedule->id)->where('role_id', $this->roles['silo']->id)->exists(), 'secondary tasks still ride the planned shifts');
        $this->assertValidSchedule($schedule);
    }

    public function test_plans_with_the_real_planner_service(): void
    {
        config(['planner.url' => 'http://planner:8000', 'planner.stage_seconds' => array_fill_keys(array_keys(config('planner.stage_seconds')), 3)]);

        try {
            Http::timeout(2)->get('http://planner:8000/health')->throw();
        } catch (\Throwable) {
            $this->markTestSkipped('The planner service is not reachable from here.');
        }

        CompanySetting::current()->update(['planner_horizon_weeks' => 1]);
        $schedule = $this->generateWeek(self::WEEK);
        $assignments = ShiftAssignment::query()->where('schedule_id', $schedule->id)->with(['role', 'shiftPattern'])->get();

        $this->assertValidSchedule($schedule);
        $leadShifts = $assignments->filter(fn (ShiftAssignment $a) => $a->role_id === $this->roles['lead']->id)->map(fn (ShiftAssignment $a) => $a->work_date->format('Y-m-d').$a->shiftPattern->slot_type)->unique();
        $this->assertCount(19, $leadShifts, 'every lead shift covered');
        $this->assertSame(15, $this->blockingGaps($schedule), 'only the 15 station shifts no operator is left for');

        foreach ($this->people as $name => $person) {
            $own = $assignments->where('employee_id', $person->id);
            $this->assertSame((float) $person->contracted_hours_per_week, $own->unique(fn ($a) => $a->work_date->format('Y-m-d'))->count() * 8.0, "{$name} works their contract hours");
            $this->assertCount(1, $own->pluck('shiftPattern.slot_type')->unique(), "{$name} works one shift type");
        }
        $this->assertFalse($assignments->where('employee_id', $this->people['Sander']->id)->contains(fn ($a) => $a->work_date->isMonday()), 'Sander never on a Monday');
    }

    public function test_refuses_when_the_planner_is_down_and_leaves_the_draft_alone(): void
    {
        config(['planner.url' => 'http://planner.test']);
        Http::fake(['planner.test/plan' => fn (Request $request) => Http::response($this->simplePlan($request->data()))]);
        $draft = $this->generateWeek(self::WEEK);
        $before = ShiftAssignment::query()->where('schedule_id', $draft->id)->orderBy('id')->pluck('id')->all();

        Http::fake(['planner.test/plan' => Http::failedConnection()]);

        $this->assertThrows(fn () => $this->generateWeek(self::WEEK, scheduleId: $draft->id), RosterPlannerUnavailableException::class);
        $this->assertNotEmpty($before);
        $this->assertSame($before, ShiftAssignment::query()->where('schedule_id', $draft->id)->orderBy('id')->pluck('id')->all(), 'the draft keeps every assignment');
    }

    public function test_refuses_without_a_configured_planner(): void
    {
        $this->assertThrows(fn () => $this->generateWeek(self::WEEK), RosterPlannerUnavailableException::class, 'not configured');
        $this->assertSame(0, Schedule::query()->count(), 'no draft is created');
    }

    public function test_generate_endpoint_queues_a_run_that_reports_the_schedule(): void
    {
        config(['planner.url' => 'http://planner.test']);
        Http::fake(['planner.test/plan' => fn (Request $request) => Http::response($this->simplePlan($request->data()))]);
        $admin = $this->admin();
        $draft = Schedule::create(['week_start_date' => self::WEEK, 'status' => Schedule::STATUS_DRAFT, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->postJson('/api/v1/schedules/suggest', $this->generatePayload(['schedule_id' => $draft->id]));

        // The sync test queue has already run the job by the time the request answers.
        $response->assertStatus(202);
        $run = $this->getJson('/api/v1/schedules/suggest/'.$response->json('run.id'))->assertOk()->json('run');
        $this->assertSame('completed', $run['status']);
        $this->assertSame($draft->id, $run['schedule']['id']);
        $this->assertNotEmpty($run['schedule']['assignments']);
    }

    public function test_generate_run_reports_why_the_planner_could_not_answer(): void
    {
        config(['planner.url' => 'http://planner.test']);
        Http::fake(['planner.test/plan' => Http::failedConnection()]);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/schedules/suggest', $this->generatePayload());

        $response->assertStatus(202);
        $run = $this->getJson('/api/v1/schedules/suggest/'.$response->json('run.id'))->assertOk()->json('run');
        $this->assertSame('failed', $run['status']);
        $this->assertStringContainsString('roster planner is not available', $run['message']);
        $this->assertNull($run['schedule']);
        $this->assertSame(0, Schedule::query()->count(), 'no draft is created');
    }

    public function test_generate_run_refuses_a_locked_schedule(): void
    {
        $admin = $this->admin();
        $locked = Schedule::create(['week_start_date' => self::WEEK, 'status' => Schedule::STATUS_APPROVED, 'created_by' => $admin->id]);

        $response = $this->actingAs($admin)->postJson('/api/v1/schedules/suggest', $this->generatePayload(['schedule_id' => $locked->id]));

        $run = $this->getJson('/api/v1/schedules/suggest/'.$response->json('run.id'))->json('run');
        $this->assertSame('failed', $run['status']);
        $this->assertStringContainsString('cannot be regenerated', $run['message']);
    }

    public function test_unknown_generate_run_is_not_found(): void
    {
        $this->actingAs($this->admin())->getJson('/api/v1/schedules/suggest/'.Str::uuid())->assertNotFound();
    }

    /**
     * Missing people on mandatory-coverage roles, as the schedule page reports them.
     */
    private function blockingGaps(Schedule $schedule): int
    {
        return app(MandatoryCoverageChecker::class)->unresolvedBlockingSlots($schedule)->sum('missingCount');
    }

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function generatePayload(array $overrides = []): array
    {
        return [
            'week_start_date' => self::WEEK,
            'line_ids' => collect($this->lines)->pluck('id')->all(),
            'slot_types' => ['day', 'afternoon', 'night'],
            'ranking_mode' => 'fair',
            ...$overrides,
        ];
    }

    /**
     * "lead|date|type" / "station|date|type|line|role" => "date|type|lead" / "date|type|roleId".
     */
    private function slotKey(string $shiftId): string
    {
        $parts = explode('|', $shiftId);

        return $parts[0] === 'lead' ? "{$parts[1]}|{$parts[2]}|lead" : "{$parts[1]}|{$parts[2]}|{$parts[4]}";
    }

    /**
     * A plain, valid answer to the problem Laravel sent - not a good plan, just a correct one
     * in the planner's format: in start order, each shift goes to the first people holding
     * its role who aren't working that day, keep this week's first shift type, are available,
     * and stay within their hours.
     *
     * @param  array<string, mixed>  $problem
     * @return array<string, mixed>
     */
    private function simplePlan(array $problem): array
    {
        $people = collect($problem['employees'])->keyBy('id');
        $worked = [];
        $minutes = [];
        $type = [];

        foreach (collect($problem['shifts'])->sortBy('start') as $shift) {
            $taken = 0;
            $length = Carbon::parse($shift['start'])->diffInMinutes(Carbon::parse($shift['end']));
            $weekday = Carbon::parse($shift['date'])->isoWeekday();

            foreach ($people as $id => $person) {
                if ($taken >= $shift['min']) {
                    break;
                }

                $free = in_array($shift['role'], $person['roles'], true)
                    && ! isset($worked[$id][$shift['date']])
                    && ($type[$id] ?? $shift['type']) === $shift['type']
                    && ($minutes[$id] ?? 0) + $length <= $person['max_hours'] * 60
                    && ! collect($person['unavailable'])->contains(fn (array $u) => $u['weekday'] === $weekday && in_array($u['type'], [null, $shift['type']], true));

                if ($free) {
                    $worked[$id][$shift['date']] = ['shift_id' => $shift['id'], 'date' => $shift['date'], 'type' => $shift['type']];
                    $minutes[$id] = ($minutes[$id] ?? 0) + $length;
                    $type[$id] = $shift['type'];
                    $taken++;
                }
            }
        }

        return [
            'status' => 'ok',
            'summary' => [],
            'totals' => $people->keys()->map(fn ($id) => ['employee_id' => $id])->values()->all(),
            'weeks' => [[
                'start' => $problem['horizon_start'],
                'employees' => collect($worked)->map(fn (array $days, $id) => [
                    'employee_id' => $id,
                    'types' => [$type[$id]],
                    'shifts' => collect($days)->filter(fn (array $s) => $s['date'] < Carbon::parse($problem['horizon_start'])->addWeek()->format('Y-m-d'))->values()->all(),
                ])->values()->all(),
            ]],
        ];
    }
}
