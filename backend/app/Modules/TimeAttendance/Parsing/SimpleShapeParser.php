<?php

namespace App\Modules\TimeAttendance\Parsing;

use App\Modules\TimeAttendance\DTOs\ColumnMappingData;

/**
 * The Simple shape (ProjectPlan.md §13a.3) is already one row per employee per day, so this
 * is a 1:1 mapping - one raw row produces exactly one ParsedRow.
 */
final class SimpleShapeParser
{
    /**
     * @param  array<int, array<string, mixed>>  $rawRows  header-keyed rows (see
     *   TimeClockImportService::indexRowsByHeader())
     * @return array<int, ParsedRow>
     */
    public function parse(array $rawRows, ColumnMappingData $mapping): array
    {
        $result = [];

        foreach ($rawRows as $index => $raw) {
            // +2: 1-based, and the header row itself is not counted as a data row
            // (ProjectPlan.md §10A.4a: "per-row errors must reference the source file's row number").
            $sourceRowNumber = $index + 2;

            $result[] = new ParsedRow(
                sourceRowNumber: $sourceRowNumber,
                employeeIdentifier: trim((string) ($raw[$mapping->employeeIdentifier] ?? '')),
                workDate: trim((string) ($raw[$mapping->workDate] ?? '')),
                clockIn: trim((string) ($raw[$mapping->clockIn] ?? '')),
                clockOut: trim((string) ($raw[$mapping->clockOut] ?? '')),
                breakMinutes: (int) ($raw[$mapping->breakMinutes] ?? 0),
            );
        }

        return $result;
    }
}
