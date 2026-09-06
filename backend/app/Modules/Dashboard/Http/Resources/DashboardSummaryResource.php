<?php

namespace App\Modules\Dashboard\Http\Resources;

use App\Modules\Dashboard\DTOs\DashboardSummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    public function __construct(
        private readonly DashboardSummaryData $data,
    ) {
        parent::__construct($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->data->toArray();
    }
}
