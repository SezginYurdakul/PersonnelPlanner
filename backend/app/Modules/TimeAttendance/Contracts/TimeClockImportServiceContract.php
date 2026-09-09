<?php

namespace App\Modules\TimeAttendance\Contracts;

use App\Modules\TimeAttendance\DTOs\ColumnMappingData;
use App\Modules\TimeAttendance\DTOs\ImportSummaryData;
use Illuminate\Http\UploadedFile;

interface TimeClockImportServiceContract
{
    /**
     * Stores the uploaded file and returns { import_token, headers } - the header row only,
     * so the frontend can render the column-mapping step (ProjectPlan.md §10A.4a point 3).
     *
     * @return array{import_token: string, headers: array<int, string>}
     */
    public function preview(string $shape, UploadedFile $file): array;

    /**
     * Re-reads the file stored at $importToken, applies $mapping, and parses + validates +
     * persists every row independently (ProjectPlan.md §13a.4).
     */
    public function commit(string $importToken, string $shape, ColumnMappingData $mapping): ImportSummaryData;
}
