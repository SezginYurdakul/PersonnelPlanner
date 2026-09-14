<?php

namespace App\Modules\Lines\DTOs;

final readonly class ShiftPatternGroupData
{
    /**
     * @param  array{day: array{start_time: string, end_time: string, crosses_midnight: bool}, afternoon: array{start_time: string, end_time: string, crosses_midnight: bool}, night: array{start_time: string, end_time: string, crosses_midnight: bool}}  $slots
     */
    public function __construct(
        public string $name,
        public array $slots,
        public bool $isActive = true,
    ) {}
}
