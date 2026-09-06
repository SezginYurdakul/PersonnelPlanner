<?php

namespace App\Modules\Lines\DTOs;

final readonly class SchedulingRoleData
{
    public function __construct(
        public string $name,
        public string $roleKind,
        public ?int $lineId = null,
        public ?bool $requiresCoverage = null,
        public ?string $attachmentType = null,
        public ?int $attachedStationRoleId = null,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'line_id' => $this->lineId,
            'role_kind' => $this->roleKind,
            'requires_coverage' => $this->requiresCoverage,
            'attachment_type' => $this->attachmentType,
            'attached_station_role_id' => $this->attachedStationRoleId,
            'is_active' => $this->isActive,
        ];
    }
}
