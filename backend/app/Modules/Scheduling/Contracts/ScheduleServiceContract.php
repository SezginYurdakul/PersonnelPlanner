<?php

namespace App\Modules\Scheduling\Contracts;

use App\Models\User;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleServiceContract
{
    /**
     * @return Collection<int, Schedule>
     */
    public function list(): Collection;

    public function find(Schedule $schedule): Schedule;

    public function create(ScheduleData $data): Schedule;

    /**
     * Updates a draft/proposed schedule's label/note (ProjectPlan.md's multi-scenario
     * support) - never touches status/assignments, only how the admin annotates it.
     */
    public function updateNote(Schedule $schedule, ?string $label, ?string $note): Schedule;

    public function approve(Schedule $schedule, User $approver): Schedule;

    public function delete(Schedule $schedule): void;
}
