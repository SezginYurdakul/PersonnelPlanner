<?php

namespace App\Modules\Leave\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'requires_approval'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
        ];
    }
}
