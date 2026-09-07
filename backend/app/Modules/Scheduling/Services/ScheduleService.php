<?php

namespace App\Modules\Scheduling\Services;

use App\Models\User;
use App\Modules\Scheduling\Contracts\ScheduleServiceContract;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Exceptions\ScheduleCannotBeApprovedException;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class ScheduleService implements ScheduleServiceContract
{
    public function __construct(private readonly MandatoryCoverageChecker $coverageChecker)
    {
    }

    public function list(): Collection
    {
        return Schedule::query()
            ->with(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role'])
            ->orderByDesc('week_start_date')
            ->get();
    }

    public function find(Schedule $schedule): Schedule
    {
        return $schedule->load(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role']);
    }

    public function create(ScheduleData $data): Schedule
    {
        return Schedule::create([
            ...$data->toArray(),
            'status' => Schedule::STATUS_DRAFT,
        ]);
    }

    /**
     * @throws ScheduleCannotBeApprovedException if a requires_coverage station within this
     *   schedule's generated scope has an unresolved unfilled slot (ProjectPlan.md §12.3b -
     *   the one hard block in this system, unlike every other rule violation).
     */
    public function approve(Schedule $schedule, User $approver): Schedule
    {
        $blockingSlots = $this->coverageChecker->unresolvedBlockingSlots($schedule);

        if ($blockingSlots->isNotEmpty()) {
            throw new ScheduleCannotBeApprovedException($blockingSlots);
        }

        $schedule->update([
            'status' => Schedule::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        // Notification hook (ProjectPlan.md §12.4, implemented in Phase 8): a
        // ScheduleApproved event/notification dispatch belongs here once the employee
        // PWA + push notification module exists.

        return $schedule->refresh();
    }

    public function delete(Schedule $schedule): void
    {
        if ($schedule->status === Schedule::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'schedule' => 'An approved schedule cannot be deleted.',
            ]);
        }

        $schedule->delete();
    }
}
