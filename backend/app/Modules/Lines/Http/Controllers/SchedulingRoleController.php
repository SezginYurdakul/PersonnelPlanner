<?php

namespace App\Modules\Lines\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Lines\Contracts\SchedulingRoleServiceContract;
use App\Modules\Lines\Http\Requests\StoreSchedulingRoleRequest;
use App\Modules\Lines\Http\Requests\SyncQualificationsRequest;
use App\Modules\Lines\Http\Requests\UpdateSchedulingRoleRequest;
use App\Modules\Lines\Http\Resources\SchedulingRoleResource;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\Models\Employee;
use Illuminate\Http\Request;

class SchedulingRoleController extends Controller
{
    public function __construct(
        private readonly SchedulingRoleServiceContract $roles,
    ) {}

    public function index(Request $request)
    {
        $roles = $this->roles->list($request->only(['line_id', 'role_kind', 'is_active']));

        return SchedulingRoleResource::collection($roles);
    }

    public function store(StoreSchedulingRoleRequest $request): SchedulingRoleResource
    {
        $role = $this->roles->create($request->toDto());
        $role->load(['line', 'attachedStation']);

        return new SchedulingRoleResource($role);
    }

    public function show(SchedulingRole $schedulingRole): SchedulingRoleResource
    {
        $schedulingRole->load(['line', 'attachedStation']);

        return new SchedulingRoleResource($schedulingRole);
    }

    public function update(UpdateSchedulingRoleRequest $request, SchedulingRole $schedulingRole): SchedulingRoleResource
    {
        $role = $this->roles->update($schedulingRole, $request->toDto());
        $role->load(['line', 'attachedStation']);

        return new SchedulingRoleResource($role);
    }

    public function destroy(SchedulingRole $schedulingRole): SchedulingRoleResource
    {
        return new SchedulingRoleResource($this->roles->deactivate($schedulingRole));
    }

    public function syncQualifications(SyncQualificationsRequest $request, Employee $employee)
    {
        $roles = $this->roles->syncEmployeeQualifications($employee, $request->roleIds());

        return SchedulingRoleResource::collection($roles);
    }
}
