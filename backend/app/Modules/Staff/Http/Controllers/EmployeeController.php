<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Staff\Contracts\EmployeeServiceContract;
use App\Modules\Staff\Http\Requests\LinkUserRequest;
use App\Modules\Staff\Http\Requests\StoreEmployeeRequest;
use App\Modules\Staff\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Staff\Http\Requests\UpdateEmploymentTermRequest;
use App\Modules\Staff\Http\Resources\EmployeeResource;
use App\Modules\Staff\Http\Resources\EmploymentTermResource;
use App\Modules\Staff\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeServiceContract $employees,
    ) {}

    public function index(Request $request)
    {
        $employees = $this->employees->list($request->only([
            'employee_type', 'agency_id', 'line_id', 'is_active', 'has_account',
        ]));

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $employee = $this->employees->create($request->toDto());
        $employee->load(['agency', 'currentEmploymentTerm']);

        return new EmployeeResource($employee);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $employee->load(['agency', 'currentEmploymentTerm']);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee = $this->employees->update($employee, $request->toDto());
        $employee->load(['agency', 'currentEmploymentTerm']);

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($this->employees->deactivate($employee));
    }

    public function linkUser(LinkUserRequest $request, Employee $employee): EmployeeResource
    {
        $user = User::findOrFail($request->integer('user_id'));

        return new EmployeeResource($this->employees->linkUser($employee, $user));
    }

    public function unlinkUser(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($this->employees->unlinkUser($employee));
    }

    public function showEmploymentTerm(Employee $employee): EmploymentTermResource
    {
        return new EmploymentTermResource($employee->currentEmploymentTerm);
    }

    public function updateEmploymentTerm(UpdateEmploymentTermRequest $request, Employee $employee): EmploymentTermResource
    {
        return new EmploymentTermResource($this->employees->updateEmploymentTerm($employee, $request->toDto()));
    }
}
