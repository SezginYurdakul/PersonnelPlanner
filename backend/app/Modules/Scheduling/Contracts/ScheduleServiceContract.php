<?php

namespace App\Modules\Scheduling\Contracts;

use App\Models\User;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Exceptions\ScheduleCannotBeApprovedException;
use App\Modules\Scheduling\Exceptions\ScheduleHasLeaveConflictsException;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

interface ScheduleServiceContract
{
    /**
     * @return Collection<int, Schedule>
     */
    public function list(): Collection;

    public function find(Schedule $schedule): Schedule;

    public function create(ScheduleData $data): Schedule;

    /**
     * Updates a draft schedule's label/note (ProjectPlan.md's multi-scenario support) -
     * never touches status/assignments, only how the admin annotates it. Only a draft
     * schedule is editable this way - see Schedule::isEditableByAdmin().
     */
    public function updateNote(Schedule $schedule, ?string $label, ?string $note): Schedule;

    /**
     * Admin submits a draft plan for the super_admin's review - locks it from further
     * admin edits until the super_admin approves or rejects it.
     */
    public function submitForReview(Schedule $schedule, User $submitter): Schedule;

    /**
     * Super_admin only. Approves a submitted (proposed) plan.
     *
     * @throws ScheduleCannotBeApprovedException if a
     *                                           requires_coverage station has an unresolved unfilled slot
     * @throws ScheduleHasLeaveConflictsException if an assignment's employee now has
     *                                            approved leave or a sick notice covering that day
     * @throws ValidationException if another schedule for the same
     *                             week is already approved
     */
    public function approve(Schedule $schedule, User $approver): Schedule;

    /**
     * Super_admin only. Sends a submitted (proposed) plan back to the admin as a draft,
     * with a required note explaining what needs to change.
     */
    public function reject(Schedule $schedule, User $rejector, string $note): Schedule;

    /**
     * Super_admin only. Reverts an already-approved plan back to draft for manual editing -
     * permanently marks it as never-again-generatable via Generate Suggestion.
     */
    public function unlock(Schedule $schedule, User $unlocker, ?string $note): Schedule;

    public function delete(Schedule $schedule): void;
}
