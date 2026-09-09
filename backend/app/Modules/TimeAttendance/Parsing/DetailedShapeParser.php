<?php

namespace App\Modules\TimeAttendance\Parsing;

use App\Modules\TimeAttendance\DTOs\ColumnMappingData;

/**
 * The Detailed shape (ProjectPlan.md §13a.3) is a sequence of timestamped events per
 * employee per day - clock-in, break-start/break-end pairs (any number of cycles),
 * clock-out. This parser groups raw event rows by (employee, work_date), sorts each
 * group's events by time, and reduces the sequence to a single ParsedRow per group.
 */
final class DetailedShapeParser
{
    private const EVENT_CLOCK_IN = 'clock_in';

    private const EVENT_CLOCK_OUT = 'clock_out';

    private const EVENT_BREAK_START = 'break_start';

    private const EVENT_BREAK_END = 'break_end';

    /**
     * @param  array<int, array<string, mixed>>  $rawRows  header-keyed rows (see
     *   TimeClockImportService::indexRowsByHeader())
     * @return array<int, ParsedRow>
     */
    public function parse(array $rawRows, ColumnMappingData $mapping): array
    {
        $groups = [];

        foreach ($rawRows as $index => $raw) {
            $sourceRowNumber = $index + 2;

            $identifier = trim((string) ($raw[$mapping->employeeIdentifier] ?? ''));
            $date = trim((string) ($raw[$mapping->workDate] ?? ''));
            $key = $identifier.'|'.$date;

            $groups[$key][] = [
                'row' => $sourceRowNumber,
                'time' => trim((string) ($raw[$mapping->eventTime] ?? '')),
                'type' => strtolower(trim((string) ($raw[$mapping->eventType] ?? ''))),
                'identifier' => $identifier,
                'date' => $date,
            ];
        }

        return array_values(array_map(
            fn (array $events) => $this->reduceGroup($events),
            $groups,
        ));
    }

    /**
     * A group's events are expected in the sequence: clock_in, [break_start, break_end]*,
     * clock_out. A malformed sequence (missing clock_in/out, unpaired breaks) is not thrown
     * here - it's encoded as a ParsedRow with an empty clockIn/clockOut, so the shared
     * validation stage in TimeClockImportService produces the standard per-row error rather
     * than a second, divergent error path.
     *
     * @param  array<int, array{row: int, time: string, type: string, identifier: string, date: string}>  $events
     */
    private function reduceGroup(array $events): ParsedRow
    {
        usort($events, fn (array $a, array $b) => $a['time'] <=> $b['time']);

        $clockIn = null;
        $clockOut = null;
        $breaks = [];
        $openBreakStart = null;
        $firstRow = $events[0]['row'];

        foreach ($events as $event) {
            switch ($event['type']) {
                case self::EVENT_CLOCK_IN:
                    $clockIn ??= $event['time'];
                    break;
                case self::EVENT_CLOCK_OUT:
                    $clockOut = $event['time'];
                    break;
                case self::EVENT_BREAK_START:
                    $openBreakStart = $event['time'];
                    break;
                case self::EVENT_BREAK_END:
                    if ($openBreakStart !== null) {
                        $breaks[] = ['start' => $openBreakStart, 'end' => $event['time']];
                        $openBreakStart = null;
                    }
                    break;
            }
        }

        return new ParsedRow(
            sourceRowNumber: $firstRow,
            employeeIdentifier: $events[0]['identifier'],
            workDate: $events[0]['date'],
            clockIn: $clockIn ?? '',
            clockOut: $clockOut ?? '',
            breakMinutes: 0,
            breakIntervals: $breaks,
        );
    }
}
