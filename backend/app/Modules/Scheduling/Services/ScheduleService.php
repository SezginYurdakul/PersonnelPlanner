<?php

namespace App\Modules\Scheduling\Services;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationDispatchService;
use App\Modules\Scheduling\Contracts\ScheduleServiceContract;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Exceptions\ScheduleCannotBeApprovedException;
use App\Modules\Scheduling\Exceptions\ScheduleHasLeaveConflictsException;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleStatusEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class ScheduleService implements ScheduleServiceContract
{
    public function __construct(
        private readonly MandatoryCoverageChecker $coverageChecker,
        private readonly LeaveConflictChecker $leaveConflicts,
        private readonly NotificationDispatchService $notifications,
        private readonly EmployeeFairnessScoreService $fairnessScores,
    ) {}

    public function list(): Collection
    {
        return Schedule::query()
            ->with(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role'])
            ->orderByDesc('week_start_date')
            ->get();
    }

    /**
     * Also reused by every status-mutating method below (create/updateNote/submitForReview/
     * approve/reject/unlock) that hands a Schedule back to ScheduleResource - without this,
     * `assignments` was never eager-loaded on those, and ScheduleResource's
     * `whenLoaded('assignments')` silently DROPS the key from the JSON response entirely
     * (not `null`, not `[]` - absent). The frontend's `schedule.assignments` then reads as
     * `undefined` right after every submit/approve/reject/unlock, wiping every assignment
     * card off the grid until the next full reload put it back via find()/show().
     */
    public function find(Schedule $schedule): Schedule
    {
        return $schedule->load(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role']);
    }

    public function create(ScheduleData $data): Schedule
    {
        return $this->find(Schedule::create([
            ...$data->toArray(),
            'status' => Schedule::STATUS_DRAFT,
        ]));
    }

    public function updateNote(Schedule $schedule, ?string $label, ?string $note): Schedule
    {
        if (! $schedule->isEditableByAdmin()) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule is locked and cannot be edited in its current status.',
            ]);
        }

        $schedule->update(['label' => $label, 'note' => $note]);

        return $this->find($schedule->refresh());
    }

    public function submitForReview(Schedule $schedule, User $submitter): Schedule
    {
        if ($schedule->status !== Schedule::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'schedule' => 'Only a draft schedule can be submitted for review.',
            ]);
        }

        $schedule->update(['status' => Schedule::STATUS_PROPOSED, 'rejection_note' => null]);

        $this->recordEvent($schedule, ScheduleStatusEvent::EVENT_SUBMITTED, $submitter);

        return $this->find($schedule->refresh());
    }

    /**
     * @throws ScheduleCannotBeApprovedException if a requires_coverage station within this
     *                                           schedule's generated scope has an unresolved unfilled slot (ProjectPlan.md §12.3b -
     *                                           the one hard block in this system, unlike every other rule violation).
     * @throws ScheduleHasLeaveConflictsException if any assignment's employee now has
     *                                            approved leave or a sick notice covering that work_date - a second hard block,
     *                                            checked after coverage so both kinds of problem can exist at once but coverage is
     *                                            reported first (it's the one §12.3b already documents as blocking).
     * @throws ValidationException if the schedule isn't in `proposed` status, or if another
     *                             schedule for the same week is already approved - multiple draft/proposed scenarios
     *                             may coexist for a week (so the admin can compare alternatives), but only one may
     *                             ever be the approved one.
     */
    public function approve(Schedule $schedule, User $approver): Schedule
    {
        if ($schedule->status !== Schedule::STATUS_PROPOSED) {
            throw ValidationException::withMessages([
                'schedule' => 'Only a schedule submitted for review can be approved.',
            ]);
        }

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

        // Catches the gap between generation and approval: an employee placed on this
        // schedule may since have had leave approved, or reported sick, for one of their
        // assigned days - neither event touches the assignment itself (LeaveConflictChecker's
        // own docblock has the full story). This is the one point that reconciles it before
        // the week goes live to the employee.
        $leaveConflicts = $this->leaveConflicts->conflictingAssignments($schedule);

        if ($leaveConflicts->isNotEmpty()) {
            throw new ScheduleHasLeaveConflictsException($leaveConflicts);
        }

        // Read before update() overwrites it: true means this schedule has been approved at
        // least once already, so THIS approval is a correction (unlock -> fix -> resubmit ->
        // approve cycle) of an already-published week, not a first-time publish. Drives both
        // the version bump and which notification fires below.
        $isCorrection = $schedule->was_approved_before;

        $schedule->update([
            'status' => Schedule::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'was_approved_before' => true,
            'version' => $isCorrection ? $schedule->version + 1 : $schedule->version,
            'rejection_note' => null,
        ]);

        $this->recordEvent($schedule, ScheduleStatusEvent::EVENT_APPROVED, $approver);
        $this->fairnessScores->recordForApprovedSchedule($schedule);

        if ($isCorrection) {
            $this->notifications->notifyScheduleCorrected($schedule);
        } else {
            $this->notifications->notifyScheduleApproved($schedule);
        }

        return $this->find($schedule->refresh());
    }

    public function reject(Schedule $schedule, User $rejector, string $note): Schedule
    {
        if ($schedule->status !== Schedule::STATUS_PROPOSED) {
            throw ValidationException::withMessages([
                'schedule' => 'Only a schedule submitted for review can be rejected.',
            ]);
        }

        $schedule->update(['status' => Schedule::STATUS_DRAFT, 'rejection_note' => $note]);

        $this->recordEvent($schedule, ScheduleStatusEvent::EVENT_REJECTED, $rejector, $note);

        return $this->find($schedule->refresh());
    }

    public function unlock(Schedule $schedule, User $unlocker, ?string $note): Schedule
    {
        if ($schedule->status !== Schedule::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'schedule' => 'Only an approved schedule can be unlocked.',
            ]);
        }

        $schedule->update([
            'status' => Schedule::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->fairnessScores->removeForUnlockedSchedule($schedule);
        $this->recordEvent($schedule, ScheduleStatusEvent::EVENT_UNLOCKED, $unlocker, $note);

        return $this->find($schedule->refresh());
    }

    public function delete(Schedule $schedule): void
    {
        // Only a draft may be deleted - once submitted for review (proposed), it's awaiting
        // a super_admin's decision (approve/reject) and must not be pulled out from under
        // that review by a regular admin deleting it outright. Mirrors isEditableByAdmin()'s
        // same "draft only" line for manual editing.
        if ($schedule->status !== Schedule::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'schedule' => $schedule->isProposed()
                    ? 'A schedule submitted for review cannot be deleted - reject it first.'
                    : 'An approved schedule cannot be deleted.',
            ]);
        }

        $schedule->delete();
    }

    private function recordEvent(Schedule $schedule, string $event, User $user, ?string $note = null): void
    {
        ScheduleStatusEvent::create([
            'schedule_id' => $schedule->id,
            'event' => $event,
            'user_id' => $user->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
