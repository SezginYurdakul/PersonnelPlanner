<?php

namespace App\Modules\Leave\Contracts;

use App\Modules\Leave\DTOs\LeaveTypeData;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Database\Eloquent\Collection;

interface LeaveTypeServiceContract
{
    /**
     * @return Collection<int, LeaveType>
     */
    public function list(): Collection;

    public function create(LeaveTypeData $data): LeaveType;
}
