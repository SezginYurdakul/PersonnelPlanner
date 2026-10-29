<?php

namespace App\Modules\Staff\Contracts;

use App\Models\User;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Staff\DTOs\EmployeeData;
use App\Modules\Staff\DTOs\EmploymentTermData;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmployeeUnavailability;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Support\Collection;

interface EmployeeServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Employee>
     */
    public function list(array $filters): Collection;

    public function create(EmployeeData $data): Employee;

    public function update(Employee $employee, EmployeeData $data): Employee;

    public function deactivate(Employee $employee): Employee;

    /**
     * Permanently removes this employee, but only when no operational history (shift
     * assignments, leave requests, shift notices, time clock entries) references them -
     * config-only rows (qualifications, unavailability, exclusions, employment terms, push
     * subscriptions) are simply cascaded away since they describe how to schedule this
     * employee, not a historical record of what happened.
     */
    public function delete(Employee $employee): void;

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

    /**
     * Replaces this employee's full set of unavailable day/slot combinations - absence of a
     * pair means available, so a new employee needs no setup.
     *
     * @param  array<int, array{day_of_week: int, slot_type: string}>  $unavailableSlots
     * @return Collection<int, EmployeeUnavailability>
     */
    public function syncUnavailability(Employee $employee, array $unavailableSlots): Collection;

    /**
     * Replaces this employee's full set of role exclusions - independent of, and layered on
     * top of, qualification (schedulingRoles()).
     *
     * @param  array<int>  $schedulingRoleIds
     * @return Collection<int, SchedulingRole>
     */
    public function syncRoleExclusions(Employee $employee, array $schedulingRoleIds): Collection;

    public function updateNotes(Employee $employee, ?string $notes): Employee;
}
