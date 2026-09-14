<?php

namespace App\Modules\Lines\Services;

use App\Modules\Lines\Contracts\ShiftPatternGroupServiceContract;
use App\Modules\Lines\DTOs\ShiftPatternGroupData;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Lines\Models\ShiftPatternGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ShiftPatternGroupService implements ShiftPatternGroupServiceContract
{
    /**
     * @return Collection<int, ShiftPatternGroup>
     */
    public function list(): Collection
    {
        return ShiftPatternGroup::query()->with('shiftPatterns')->orderBy('name')->get();
    }

    public function create(ShiftPatternGroupData $data): ShiftPatternGroup
    {
        return DB::transaction(function () use ($data) {
            $group = ShiftPatternGroup::create([
                'name' => $data->name,
                'is_active' => $data->isActive,
            ]);

            $this->syncSlots($group, $data);

            return $group->load('shiftPatterns');
        });
    }

    public function update(ShiftPatternGroup $group, ShiftPatternGroupData $data): ShiftPatternGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update(['name' => $data->name, 'is_active' => $data->isActive]);

            $this->syncSlots($group, $data);

            return $group->refresh()->load('shiftPatterns');
        });
    }

    public function deactivate(ShiftPatternGroup $group): ShiftPatternGroup
    {
        DB::transaction(function () use ($group) {
            $group->update(['is_active' => false]);
            $group->shiftPatterns()->update(['is_active' => false]);
        });

        return $group->refresh();
    }

    private function syncSlots(ShiftPatternGroup $group, ShiftPatternGroupData $data): void
    {
        foreach ($data->slots as $slotType => $hours) {
            ShiftPattern::query()->updateOrCreate(
                ['shift_pattern_group_id' => $group->id, 'slot_type' => $slotType],
                [
                    'name' => ucfirst($slotType).' Shift',
                    'start_time' => $hours['start_time'],
                    'end_time' => $hours['end_time'],
                    'crosses_midnight' => $hours['crosses_midnight'],
                    'is_active' => $data->isActive,
                ],
            );
        }
    }
}
