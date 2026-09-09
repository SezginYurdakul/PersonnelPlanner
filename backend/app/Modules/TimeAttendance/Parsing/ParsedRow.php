<?php

namespace App\Modules\TimeAttendance\Parsing;

/**
 * Shape-agnostic intermediate representation both SimpleShapeParser and DetailedShapeParser
 * reduce their input to, so downstream matching/validation/error-collection
 * (TimeClockImportService::importRow()) is shared code, not duplicated per shape
 * (ProjectPlan.md §13a.3).
 */
final readonly class ParsedRow
{
    /**
     * @param  array<int, array{start: string, end: string}>  $breakIntervals  empty for the
     *   Simple shape; one entry per break-start/break-end pair for the Detailed shape.
     */
    public function __construct(
        public int $sourceRowNumber,
        public string $employeeIdentifier,
        public string $workDate,
        public string $clockIn,
        public string $clockOut,
        public int $breakMinutes,
        public array $breakIntervals = [],
    ) {
    }
}
