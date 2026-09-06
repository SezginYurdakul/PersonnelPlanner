<?php

namespace App\Modules\Lines\Contracts;

use App\Modules\Lines\DTOs\ShiftPatternData;
use App\Modules\Lines\Models\ShiftPattern;
use Illuminate\Database\Eloquent\Collection;

interface ShiftPatternServiceContract
{
    /**
     * @return Collection<int, ShiftPattern>
     */
    public function list(): Collection;

    public function create(ShiftPatternData $data): ShiftPattern;

    public function update(ShiftPattern $shiftPattern, ShiftPatternData $data): ShiftPattern;

    public function deactivate(ShiftPattern $shiftPattern): ShiftPattern;
}
