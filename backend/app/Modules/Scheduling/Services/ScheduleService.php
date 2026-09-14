<?php

namespace App\Modules\Scheduling\Services;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationDispatchService;
use App\Modules\Scheduling\Contracts\ScheduleServiceContract;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Exceptions\ScheduleCannotBeApprovedException;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class ScheduleService implements ScheduleServiceContract
{
    public function __construct(
        private readonly MandatoryCoverageChecker $coverageChecker,
        private readonly NotificationDispatchService $notifications,
    ) {}

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

    public function updateNote(Schedule $schedule, ?string $label, ?string $note): Schedule
    {
        $schedule->update(['label' => $label, 'note' => $note]);

        return $schedule->refresh();
    }

    /**
     * @throws ScheduleCannotBeApprovedException if a requires_coverage station within this
     *                                           schedule's generated scope has an unresolved unfilled slot (ProjectPlan.md §12.3b -
     *                                           the one hard block in this system, unlike every other rule violation).
     * @throws ValidationException if another schedule for the same week is already
     *                             approved - multiple draft/proposed scenarios may coexist for a week (so the admin
     *                             can compare alternatives), but only one may ever be the approved one.
     */
    public function approve(Schedule $schedule, User $approver): Schedule
    {
        $alreadyApprovedForWeek = Schedule::query()
            ->where('week_start_date', $schedule->week_start_date)
            ->where('status', Schedule::STATUS_APPROVED)
            ->where('id', '!=', $schedule->id)
            ->exists();

        if ($alreadyApprovedForWeek) {
            throw ValidationException::withMessages([
                'schedule' => 'Another schedule for this week has already been approved. Reject or delete it before approving this one.',
            ]);
        }

        $blockingSlots = $this->coverageChecker->unresolvedBlockingSlots($schedule);

        if ($blockingSlots->isNotEmpty()) {
            throw new ScheduleCannotBeApprovedException($blockingSlots);
        }

        $schedule->update([
            'status' => Schedule::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->notifications->notifyScheduleApproved($schedule);

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
