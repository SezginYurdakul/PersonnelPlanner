<?php

namespace App\Modules\Staff\Services;

use App\Modules\Staff\Contracts\AgencyServiceContract;
use App\Modules\Staff\DTOs\AgencyData;
use App\Modules\Staff\Models\Agency;
use Illuminate\Database\Eloquent\Collection;

final class AgencyService implements AgencyServiceContract
{
    /**
     * @return Collection<int, Agency>
     */
    public function list(): Collection
    {
        return Agency::query()->orderBy('name')->get();
    }

    public function create(AgencyData $data): Agency
    {
        return Agency::create($data->toArray());
    }

    public function update(Agency $agency, AgencyData $data): Agency
    {
        $agency->update($data->toArray());

        return $agency->refresh();
    }

    public function delete(Agency $agency): void
    {
        $agency->delete();
    }
}
