<?php

namespace App\Modules\Lines\Contracts;

use App\Modules\Lines\DTOs\ShiftPatternGroupData;
use App\Modules\Lines\Models\ShiftPatternGroup;
use Illuminate\Database\Eloquent\Collection;

interface ShiftPatternGroupServiceContract
{
    /**
     * @return Collection<int, ShiftPatternGroup>
     */
    public function list(): Collection;

    /**
     * Creates a group and its three day/afternoon/night ShiftPattern rows together - a
     * group is meaningless without all three slot hours defined.
     */
    public function create(ShiftPatternGroupData $data): ShiftPatternGroup;

    public function update(ShiftPatternGroup $group, ShiftPatternGroupData $data): ShiftPatternGroup;

    /**
     * Deactivates the group and its patterns rather than deleting them (a line or
     * historical shift_assignment may still reference them).
     */
    public function deactivate(ShiftPatternGroup $group): ShiftPatternGroup;
}
