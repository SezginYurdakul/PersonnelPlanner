<?php

namespace App\Modules\Lines\DTOs;

final readonly class PayRateSurchargeRuleData
{
    /**
     * @param  array<int>  $daysOfWeek
     */
    public function __construct(
        public string $name,
        public array $daysOfWeek,
        public string $startTime,
        public string $endTime,
        public bool $crossesMidnight,
        public float $surchargePercentage,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'days_of_week' => $this->daysOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'crosses_midnight' => $this->crossesMidnight,
            'surcharge_percentage' => $this->surchargePercentage,
            'is_active' => $this->isActive,
        ];
    }
}
