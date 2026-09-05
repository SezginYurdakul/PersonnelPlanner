<?php

namespace App\Modules\Staff\DTOs;

use Illuminate\Support\Carbon;

final readonly class EmploymentTermData
{
    public function __construct(
        public int $maxWeeklyHours,
        public Carbon $effectiveFrom,
        public ?Carbon $effectiveTo = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_weekly_hours' => $this->maxWeeklyHours,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
