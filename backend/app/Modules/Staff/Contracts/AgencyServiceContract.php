<?php

namespace App\Modules\Staff\Contracts;

use App\Modules\Staff\DTOs\AgencyData;
use App\Modules\Staff\Models\Agency;
use Illuminate\Database\Eloquent\Collection;

interface AgencyServiceContract
{
    /**
     * @return Collection<int, Agency>
     */
    public function list(): Collection;

    public function create(AgencyData $data): Agency;

    public function update(Agency $agency, AgencyData $data): Agency;

    public function delete(Agency $agency): void;
}
