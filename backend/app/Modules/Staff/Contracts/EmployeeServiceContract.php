<?php

namespace App\Modules\Staff\Contracts;

use App\Models\User;
use App\Modules\Staff\DTOs\EmployeeData;
use App\Modules\Staff\DTOs\EmploymentTermData;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(array $filters): LengthAwarePaginator;

    public function create(EmployeeData $data): Employee;

    public function update(Employee $employee, EmployeeData $data): Employee;

    public function deactivate(Employee $employee): Employee;

    /**
     * Link an existing, not-yet-linked user account to this employee (ProjectPlan.md §11.2a).
     */
    public function linkUser(Employee $employee, User $user): Employee;

    public function unlinkUser(Employee $employee): Employee;

    /**
     * Close out the employee's current employment term (if any) and open a new one,
     * preserving history rather than overwriting it (ProjectPlan.md §17A.4).
     */
    public function updateEmploymentTerm(Employee $employee, EmploymentTermData $data): EmploymentTerm;
}
