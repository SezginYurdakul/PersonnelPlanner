<?php

namespace App\Modules\Lines\Models;

use App\Modules\Staff\Models\Employee;
use Database\Factories\LineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'is_active'])]
class Line extends Model
{
    /** @use HasFactory<LineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SchedulingRole, $this>
     */
    public function schedulingRoles(): HasMany
    {
        return $this->hasMany(SchedulingRole::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function defaultEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'default_line_id');
    }
}
