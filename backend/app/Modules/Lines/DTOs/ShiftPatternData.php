<?php

namespace App\Modules\Lines\DTOs;

final readonly class ShiftPatternData
{
    public function __construct(
        public string $name,
        public string $startTime,
        public string $endTime,
        public bool $crossesMidnight,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'crosses_midnight' => $this->crossesMidnight,
            'is_active' => $this->isActive,
        ];
    }
}
