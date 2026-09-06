<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Contracts\LeaveTypeServiceContract;
use App\Modules\Leave\DTOs\LeaveTypeData;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Database\Eloquent\Collection;

final class LeaveTypeService implements LeaveTypeServiceContract
{
    /**
     * @return Collection<int, LeaveType>
     */
    public function list(): Collection
    {
        return LeaveType::query()->orderBy('name')->get();
    }

    public function create(LeaveTypeData $data): LeaveType
    {
        return LeaveType::create($data->toArray());
    }
}
