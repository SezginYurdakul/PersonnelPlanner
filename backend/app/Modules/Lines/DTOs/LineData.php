<?php

namespace App\Modules\Lines\DTOs;

final readonly class LineData
{
    public function __construct(
        public string $name,
        public string $code,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'is_active' => $this->isActive,
        ];
    }
}
