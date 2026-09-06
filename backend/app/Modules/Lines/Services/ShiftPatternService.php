<?php

namespace App\Modules\Lines\Services;

use App\Modules\Lines\Contracts\ShiftPatternServiceContract;
use App\Modules\Lines\DTOs\ShiftPatternData;
use App\Modules\Lines\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Collection;

final class ShiftPatternService implements ShiftPatternServiceContract
{
    /**
     * @return Collection<int, ShiftPattern>
     */
    public function list(): Collection
    {
        return ShiftPattern::query()->orderBy('start_time')->get();
    }

    public function create(ShiftPatternData $data): ShiftPattern
    {
        return ShiftPattern::create($data->toArray());
    }

    public function update(ShiftPattern $shiftPattern, ShiftPatternData $data): ShiftPattern
    {
        $shiftPattern->update($data->toArray());

        return $shiftPattern->refresh();
    }

    public function deactivate(ShiftPattern $shiftPattern): ShiftPattern
    {
        $shiftPattern->update(['is_active' => false]);

        return $shiftPattern->refresh();
    }
}
