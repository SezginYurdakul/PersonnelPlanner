<?php

namespace App\Modules\Lines\Services;

use App\Modules\Lines\Contracts\SchedulingRoleServiceContract;
use App\Modules\Lines\DTOs\SchedulingRoleData;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * @param  array<int>  $roleIds
     * @return Collection<int, SchedulingRole>
     */
    public function syncEmployeeQualifications(Employee $employee, array $roleIds): Collection
    {
        $employee->schedulingRoles()->sync($roleIds);

        return $employee->schedulingRoles()->with(['line'])->get();
    }

    /**
     * Enforces the role_kind-driven field rules from ProjectPlan.md §17A.5a:
     * requires_coverage is meaningless for secondary tasks, attachment_type/
     * attached_station_role_id are meaningless for stations.
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
        } else {
            $attributes['requires_coverage'] = false;

            if ($data->attachmentType !== 'station') {
                $attributes['attached_station_role_id'] = null;
            }
        }

        return $attributes;
    }
}
