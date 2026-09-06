<?php

namespace App\Modules\Leave\DTOs;

final readonly class LeaveTypeData
{
    public function __construct(
        public string $name,
        public bool $requiresApproval = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'requires_approval' => $this->requiresApproval,
        ];
    }
}
