<?php

namespace App\Modules\Lines\Services;

use App\Modules\Lines\Contracts\LineServiceContract;
use App\Modules\Lines\DTOs\LineData;
use App\Modules\Lines\Models\Line;
use Illuminate\Database\Eloquent\Collection;

final class LineService implements LineServiceContract
{
    /**
     * @return Collection<int, Line>
     */
    public function list(): Collection
    {
        return Line::query()->orderBy('name')->get();
    }

    public function create(LineData $data): Line
    {
        return Line::create($data->toArray());
    }

    public function update(Line $line, LineData $data): Line
    {
        $line->update($data->toArray());

        return $line->refresh();
    }

    public function deactivate(Line $line): Line
    {
        $line->update(['is_active' => false]);

        return $line->refresh();
    }
}
