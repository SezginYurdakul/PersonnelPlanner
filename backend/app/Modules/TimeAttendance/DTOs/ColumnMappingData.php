<?php

namespace App\Modules\TimeAttendance\DTOs;

/**
 * Target-field -> source-header mapping the admin confirms during the column-mapping step
 * (ProjectPlan.md §10A.4a point 3). Keys are the fixed target field names each shape
 * requires; values are the actual header text from the admin's uploaded file, so the
 * parser can look up `$rawRow[$mapping->clockIn]` deterministically regardless of what the
 * source file happened to name its columns.
 */
final readonly class ColumnMappingData
{
    public function __construct(
        public string $employeeIdentifier,
        public string $workDate,
        public ?string $clockIn = null,
        public ?string $clockOut = null,
        public ?string $breakMinutes = null,
        public ?string $eventTime = null,
        public ?string $eventType = null,
    ) {
    }
}
