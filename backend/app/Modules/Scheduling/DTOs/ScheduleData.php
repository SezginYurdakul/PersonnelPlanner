<?php

namespace App\Modules\Scheduling\DTOs;

final readonly class ScheduleData
{
    public function __construct(
        public string $weekStartDate,
        public int $createdBy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'week_start_date' => $this->weekStartDate,
            'created_by' => $this->createdBy,
        ];
    }
}
