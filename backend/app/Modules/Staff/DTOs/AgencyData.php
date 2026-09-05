<?php

namespace App\Modules\Staff\DTOs;

final readonly class AgencyData
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
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
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'is_active' => $this->isActive,
        ];
    }
}
