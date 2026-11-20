<?php

namespace App\Modules\Lines\Services;

use App\Modules\Lines\Contracts\SchedulingRoleServiceContract;
use App\Modules\Lines\DTOs\SchedulingRoleData;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class SchedulingRoleService implements SchedulingRoleServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SchedulingRole>
     */
    public function list(array $filters): Collection
    {
        $query = SchedulingRole::query()->with(['line', 'attachedStation']);

        if (isset($filters['line_id'])) {
            $query->where('line_id', $filters['line_id']);
        }

        if (isset($filters['role_kind'])) {
            $query->where('role_kind', $filters['role_kind']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->get();
    }

    public function create(SchedulingRoleData $data): SchedulingRole
    {
        return SchedulingRole::create($this->normalize($data));
    }

    public function update(SchedulingRole $role, SchedulingRoleData $data): SchedulingRole
    {
        $role->update($this->normalize($data));

        return $role->refresh();
    }

    public function deactivate(SchedulingRole $role): SchedulingRole
    {
        $role->update(['is_active' => false]);

        return $role->refresh();
    }

    public function delete(SchedulingRole $role): void
    {
        if ($role->shiftAssignments()->exists()) {
            throw ValidationException::withMessages([
                'role' => __('lines.role_has_operational_history'),
            ]);
        }

        $role->delete();
    }

    /**
     * @param  array<int>  $roleIds
     * @param  ?array<int>  $preferredRoleIds
     * @return Collection<int, SchedulingRole>
     */
    public function syncEmployeeQualifications(Employee $employee, array $roleIds, ?array $preferredRoleIds = null): Collection
    {
        $preferredRoleIds = array_map('intval', $preferredRoleIds ?? $employee->schedulingRoles()
            ->wherePivot('is_preferred', true)
            ->pluck('scheduling_roles.id')
            ->all());

        $employee->schedulingRoles()->sync(collect($roleIds)
            ->mapWithKeys(fn ($roleId) => [(int) $roleId => ['is_preferred' => in_array((int) $roleId, $preferredRoleIds, true)]])
            ->all());

        return $employee->schedulingRoles()->with(['line'])->get();
    }

    /**
     * Enforces the role_kind-driven field rules from ProjectPlan.md §17A.5a:
     * attachment_type/attached_station_role_id are meaningless for stations.
     *
     * requires_coverage is meaningful for BOTH kinds: a mandatory secondary task (e.g. a
     * fault-response duty) is staffed the same way as any other secondary task - from
     * whoever already holds this shift, never a new hire - but going unfilled still blocks
     * approval, exactly like a mandatory station (MandatoryCoverageChecker treats both the
     * same way).
     *
     * @return array<string, mixed>
     */
    private function normalize(SchedulingRoleData $data): array
    {
        $attributes = $data->toArray();

        if ($data->roleKind === SchedulingRole::KIND_STATION) {
            $attributes['requires_coverage'] = $data->requiresCoverage ?? true;
            $attributes['attachment_type'] = null;
            $attributes['attached_station_role_id'] = null;
            $attributes['allows_second_person'] = $data->allowsSecondPerson ?? false;
            $attributes['min_headcount'] = $data->minHeadcount ?? 1;
        } else {
            $attributes['requires_coverage'] = $data->requiresCoverage ?? false;
            $attributes['allows_second_person'] = false;
            $attributes['min_headcount'] = 1;

            if ($data->attachmentType !== 'station') {
                $attributes['attached_station_role_id'] = null;
            }
        }

        return $attributes;
    }
}
