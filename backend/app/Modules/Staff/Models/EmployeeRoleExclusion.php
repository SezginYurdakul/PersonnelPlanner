<?php

namespace App\Modules\Staff\Models;

use App\Modules\Lines\Models\SchedulingRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduling role this employee must never be assigned to - independent of, and layered
 * on top of, `employee_scheduling_roles` (qualification). An employee can be qualified for
 * a role and still be excluded from it (e.g. a medical restriction or personal exemption
 * unrelated to skill).
 */
#[Fillable([
    'employee_id',
    'scheduling_role_id',
])]
class EmployeeRoleExclusion extends Model
{
    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<SchedulingRole, $this>
     */
    public function schedulingRole(): BelongsTo
    {
        return $this->belongsTo(SchedulingRole::class);
    }
}
