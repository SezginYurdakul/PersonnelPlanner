<?php

namespace App\Modules\Lines\Contracts;

use App\Modules\Lines\DTOs\SchedulingRoleData;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

interface SchedulingRoleServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SchedulingRole>
     */
    public function list(array $filters): Collection;

    public function create(SchedulingRoleData $data): SchedulingRole;

    public function update(SchedulingRole $role, SchedulingRoleData $data): SchedulingRole;

    public function deactivate(SchedulingRole $role): SchedulingRole;

    /**
     * Replace an employee's full set of qualified roles (ProjectPlan.md §11a.3).
     *
     * @param  array<int>  $roleIds
     * @return Collection<int, SchedulingRole>
     */
    public function syncEmployeeQualifications(Employee $employee, array $roleIds): Collection;
}
