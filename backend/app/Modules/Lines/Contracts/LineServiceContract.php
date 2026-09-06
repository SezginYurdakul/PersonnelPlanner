<?php

namespace App\Modules\Lines\Contracts;

use App\Modules\Lines\DTOs\LineData;
use App\Modules\Lines\Models\Line;
use Illuminate\Database\Eloquent\Collection;

interface LineServiceContract
{
    /**
     * @return Collection<int, Line>
     */
    public function list(): Collection;

    public function create(LineData $data): Line;

    public function update(Line $line, LineData $data): Line;

    /**
     * Deactivates the line rather than hard-deleting it (ProjectPlan.md §17A.10 -
     * a line referenced by shift assignments must never be destroyed outright).
     */
    public function deactivate(Line $line): Line;
}
