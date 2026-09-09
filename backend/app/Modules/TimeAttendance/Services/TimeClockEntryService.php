<?php

namespace App\Modules\TimeAttendance\Services;

use App\Modules\TimeAttendance\Contracts\TimeClockEntryServiceContract;
use App\Modules\TimeAttendance\DTOs\TimeClockEntryData;
use App\Modules\TimeAttendance\Models\TimeClockEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TimeClockEntryService implements TimeClockEntryServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TimeClockEntry>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = TimeClockEntry::query()->with(['employee', 'breaks']);

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('work_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('work_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('work_date')->paginate();
    }

    public function create(TimeClockEntryData $data): TimeClockEntry
    {
        $entry = TimeClockEntry::create([
            ...$data->toArray(),
            'source' => TimeClockEntry::SOURCE_MANUAL,
        ]);

        return $entry->refresh();
    }

    public function update(TimeClockEntry $entry, TimeClockEntryData $data): TimeClockEntry
    {
        $entry->update([
            ...$data->toArray(),
            // A manual correction always flips the entry's source (ProjectPlan.md §17.10a -
            // "or that an admin corrected/created it by hand"), regardless of how it
            // originally got here.
            'source' => TimeClockEntry::SOURCE_MANUAL,
        ]);

        return $entry->refresh();
    }

    public function delete(TimeClockEntry $entry): void
    {
        $entry->delete();
    }
}
