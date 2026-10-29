<?php

namespace App\Modules\Staff\Services;

use App\Models\User;
use App\Modules\Staff\Contracts\EmployeeServiceContract;
use App\Modules\Staff\DTOs\EmployeeData;
use App\Modules\Staff\DTOs\EmploymentTermData;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EmployeeService implements EmployeeServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Employee>
     */
    public function list(array $filters): Collection
    {
        $query = Employee::query()->with(['agency', 'currentEmploymentTerm', 'user']);

        if (isset($filters['employee_type'])) {
            $query->where('employee_type', $filters['employee_type']);
        }

        if (isset($filters['agency_id'])) {
            $query->where('agency_id', $filters['agency_id']);
        }

        if (isset($filters['line_id'])) {
            $query->where('default_line_id', $filters['line_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['has_account'])) {
            $hasAccount = filter_var($filters['has_account'], FILTER_VALIDATE_BOOLEAN);
            $hasAccount ? $query->whereNotNull('user_id') : $query->whereNull('user_id');
        }

        return $query->orderBy('last_name')->orderBy('first_name')->get();
    }

    public function create(EmployeeData $data): Employee
    {
        return Employee::create($data->toArray());
    }

    public function update(Employee $employee, EmployeeData $data): Employee
    {
        $employee->update($data->toArray());

        return $employee->refresh();
    }

    public function deactivate(Employee $employee): Employee
    {
        $employee->update(['is_active' => false]);

        return $employee->refresh();
    }

    public function delete(Employee $employee): void
    {
        if ($employee->shiftAssignments()->exists()
            || $employee->leaveRequests()->exists()
            || $employee->shiftNotices()->exists()
            || $employee->timeClockEntries()->exists()
        ) {
            throw ValidationException::withMessages([
                'employee' => __('staff.has_operational_history'),
            ]);
        }

        $employee->forceDelete();
    }

    public function linkUser(Employee $employee, User $user): Employee
    {
        $alreadyLinkedElsewhere = Employee::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $employee->id)
            ->exists();

        if ($alreadyLinkedElsewhere) {
            throw ValidationException::withMessages([
                'user_id' => __('staff.user_already_linked'),
            ]);
        }

        $employee->update(['user_id' => $user->id]);

        return $employee->refresh();
    }

    public function unlinkUser(Employee $employee): Employee
    {
        $employee->update(['user_id' => null]);

        return $employee->refresh();
    }

    public function updateEmploymentTerm(Employee $employee, EmploymentTermData $data): EmploymentTerm
    {
        $current = $employee->currentEmploymentTerm;

        if ($current !== null) {
            $current->update(['effective_to' => $data->effectiveFrom->copy()->subDay()]);
        }

        return $employee->employmentTerms()->create($data->toArray());
    }

    public function syncUnavailability(Employee $employee, array $unavailableSlots): Collection
    {
        $employee->unavailabilities()->delete();

        $employee->unavailabilities()->createMany($unavailableSlots);

        return $employee->unavailabilities()->get();
    }

    public function syncRoleExclusions(Employee $employee, array $schedulingRoleIds): Collection
    {
        $employee->roleExclusions()->delete();

        $employee->roleExclusions()->createMany(
            collect($schedulingRoleIds)->map(fn (int $roleId) => ['scheduling_role_id' => $roleId])->all()
        );

        return $employee->roleExclusions()->with('schedulingRole.line')->get();
    }

    public function updateNotes(Employee $employee, ?string $notes): Employee
    {
        $employee->update(['notes' => $notes]);

        return $employee->refresh();
    }
}
