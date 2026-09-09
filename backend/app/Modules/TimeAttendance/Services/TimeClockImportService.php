<?php

namespace App\Modules\TimeAttendance\Services;

use App\Modules\TimeAttendance\Contracts\TimeClockImportServiceContract;
use App\Modules\TimeAttendance\DTOs\ColumnMappingData;
use App\Modules\TimeAttendance\DTOs\ImportSummaryData;
use App\Modules\TimeAttendance\Imports\RawSheetImport;
use App\Modules\TimeAttendance\Parsing\DetailedShapeParser;
use App\Modules\TimeAttendance\Parsing\SimpleShapeParser;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use App\Modules\TimeAttendance\DTOs\ImportRowResult;
use App\Modules\TimeAttendance\Models\TimeClockEntry;
use App\Modules\TimeAttendance\Parsing\ParsedRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

final class TimeClockImportService implements TimeClockImportServiceContract
{
    private const STORAGE_DISK = 'local';

    private const STORAGE_DIRECTORY = 'time-clock-imports';

    public function __construct(
        private readonly SimpleShapeParser $simpleShapeParser,
        private readonly DetailedShapeParser $detailedShapeParser,
    ) {
    }

    public function preview(string $shape, UploadedFile $file): array
    {
        $path = $file->store(self::STORAGE_DIRECTORY, self::STORAGE_DISK);

        $rows = $this->readRawRows($path);
        $headerRow = $rows[0] ?? [];

        return [
            'import_token' => $path,
            'headers' => array_map(fn ($value) => (string) $value, $headerRow),
        ];
    }

    public function commit(string $importToken, string $shape, ColumnMappingData $mapping): ImportSummaryData
    {
        $rows = $this->readRawRows($importToken);
        $headerRow = $rows[0] ?? [];
        $dataRows = array_slice($rows, 1);

        $indexedRows = $this->indexRowsByHeader($dataRows, $headerRow);

        $parsedRows = $shape === 'simple'
            ? $this->simpleShapeParser->parse($indexedRows, $mapping)
            : $this->detailedShapeParser->parse($indexedRows, $mapping);

        $results = collect($parsedRows)->map(fn (ParsedRow $row) => $this->importRow($row, $shape));

        return ImportSummaryData::fromResults($results);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRawRows(string $storedPath): array
    {
        $sheets = Excel::toArray(new RawSheetImport(), $storedPath, self::STORAGE_DISK);

        return $sheets[0] ?? [];
    }

    /**
     * Re-keys each raw positional row (column index => value) by the file's actual header
     * text (column name => value), so parsers can look up
     * `$row[$mapping->employeeIdentifier]` by the header string the admin selected during
     * column mapping (ProjectPlan.md §10A.4a point 3), rather than by column position.
     *
     * @param  array<int, array<int, mixed>>  $dataRows
     * @param  array<int, mixed>  $headerRow
     * @return array<int, array<string, mixed>>
     */
    private function indexRowsByHeader(array $dataRows, array $headerRow): array
    {
        return array_map(function (array $row) use ($headerRow): array {
            $indexed = [];
            foreach ($headerRow as $index => $header) {
                $indexed[(string) $header] = $row[$index] ?? null;
            }

            return $indexed;
        }, $dataRows);
    }

    private function importRow(ParsedRow $row, string $shape): ImportRowResult
    {
        $employee = Employee::query()->where('email', $row->employeeIdentifier)->first();

        if ($employee === null) {
            return ImportRowResult::error(
                $row->sourceRowNumber,
                'employee_not_found',
                __('time_attendance.import.employee_not_found', ['identifier' => $row->employeeIdentifier]),
            );
        }

        if ($row->workDate === '' || $row->clockIn === '' || $row->clockOut === '') {
            return ImportRowResult::error(
                $row->sourceRowNumber,
                'invalid_time_format',
                __('time_attendance.import.invalid_time_format'),
            );
        }

        try {
            $workDate = Carbon::parse($row->workDate);
            $clockIn = Carbon::parse($row->workDate.' '.$row->clockIn);
            $clockOut = Carbon::parse($row->workDate.' '.$row->clockOut);
        } catch (\Throwable) {
            return ImportRowResult::error(
                $row->sourceRowNumber,
                'invalid_time_format',
                __('time_attendance.import.invalid_time_format'),
            );
        }

        if ($clockOut->lte($clockIn)) {
            // Handle a shift crossing midnight: if clock_out reads earlier than clock_in on
            // the same nominal date, assume it lands on the following calendar day.
            $clockOut = $clockOut->copy()->addDay();
        }

        if ($clockOut->lte($clockIn)) {
            return ImportRowResult::error(
                $row->sourceRowNumber,
                'clock_out_before_clock_in',
                __('time_attendance.import.clock_out_before_clock_in'),
            );
        }

        $spanMinutes = $clockIn->diffInMinutes($clockOut);

        $parsedBreaks = [];
        foreach ($row->breakIntervals as $interval) {
            try {
                $breakStart = Carbon::parse($row->workDate.' '.$interval['start']);
                $breakEnd = Carbon::parse($row->workDate.' '.$interval['end']);
            } catch (\Throwable) {
                return ImportRowResult::error(
                    $row->sourceRowNumber,
                    'invalid_break_interval',
                    __('time_attendance.import.invalid_break_interval'),
                );
            }

            if ($breakEnd->lte($breakStart) || $breakStart->lt($clockIn) || $breakEnd->gt($clockOut)) {
                return ImportRowResult::error(
                    $row->sourceRowNumber,
                    'invalid_break_interval',
                    __('time_attendance.import.invalid_break_interval'),
                );
            }

            $parsedBreaks[] = ['start' => $breakStart, 'end' => $breakEnd];
        }

        // Simple shape carries its own break-minutes total; Detailed shape derives it by
        // summing the individually-captured break intervals (ProjectPlan.md §17.10a).
        $breakMinutes = $parsedBreaks !== []
            ? array_sum(array_map(fn (array $b) => $b['start']->diffInMinutes($b['end']), $parsedBreaks))
            : $row->breakMinutes;

        if ($breakMinutes < 0 || $breakMinutes > $spanMinutes) {
            return ImportRowResult::error(
                $row->sourceRowNumber,
                'invalid_break_minutes',
                __('time_attendance.import.invalid_break_minutes'),
            );
        }

        $shiftAssignment = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->first();

        $entry = DB::transaction(function () use ($employee, $shiftAssignment, $workDate, $clockIn, $clockOut, $breakMinutes, $shape, $parsedBreaks): TimeClockEntry {
            $entry = TimeClockEntry::create([
                'employee_id' => $employee->id,
                'shift_assignment_id' => $shiftAssignment?->id,
                'work_date' => $workDate,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_minutes' => $breakMinutes,
                'source' => $shape === 'simple' ? TimeClockEntry::SOURCE_IMPORT_SIMPLE : TimeClockEntry::SOURCE_IMPORT_DETAILED,
            ]);

            foreach ($parsedBreaks as $interval) {
                $entry->breaks()->create([
                    'break_start' => $interval['start'],
                    'break_end' => $interval['end'],
                ]);
            }

            return $entry;
        });

        return ImportRowResult::imported($row->sourceRowNumber, $entry->id);
    }
}
