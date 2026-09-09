<?php

namespace App\Modules\TimeAttendance\Contracts;

use App\Modules\TimeAttendance\DTOs\TimeClockEntryData;
use App\Modules\TimeAttendance\Models\TimeClockEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TimeClockEntryServiceContract
{
    /**
     * @param  array{employee_id?: int, date_from?: string, date_to?: string}  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function create(TimeClockEntryData $data): TimeClockEntry;

    public function update(TimeClockEntry $entry, TimeClockEntryData $data): TimeClockEntry;

    public function delete(TimeClockEntry $entry): void;
}
