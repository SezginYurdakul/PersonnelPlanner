<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Owns the role-specific rotation signal (ProjectPlan.md §12.6 item 4, admin-configurable
 * via CompanySetting::role_rotation_enabled): "when did this employee last do this exact
 * role" - a second ranking dimension alongside the cumulative hours fairness score, so an
 * employee balanced in total hours isn't still repeatedly re-assigned the same role/task
 * for weeks (confirmed happening in the factory's real paper schedules - see §12.6's
 * intro). Scoped to APPROVED schedules only, mirroring EmployeeFairnessScoreService's own
 * "only real, confirmed history counts" philosophy - a draft/rejected schedule never
 * happened, so it should never influence rotation either.
 */
final class RoleRotationService
{
    /**
     * @return Collection<string, Carbon> keyed "employeeId|roleId" =>
     *                                    most recent approved work_date
     */
    public function lastAssignedDatesByEmployeeAndRole(): Collection
    {
        return ShiftAssignment::query()
            ->whereHas('schedule', fn ($q) => $q->where('status', Schedule::STATUS_APPROVED))
            ->whereNotNull('role_id')
            ->get(['employee_id', 'role_id', 'work_date'])
            ->groupBy(fn (ShiftAssignment $a) => "{$a->employee_id}|{$a->role_id}")
            ->map(fn (Collection $rows) => $rows->max('work_date'));
    }
}
