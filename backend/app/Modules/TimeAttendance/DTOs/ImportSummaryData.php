<?php

namespace App\Modules\TimeAttendance\DTOs;

use Illuminate\Support\Collection;

final readonly class ImportSummaryData
{
    /**
     * @param  Collection<int, ImportRowResult>  $errors
     */
    public function __construct(
        public int $importedCount,
        public int $errorCount,
        public Collection $errors,
    ) {
    }

    /**
     * @param  Collection<int, ImportRowResult>  $results
     */
    public static function fromResults(Collection $results): self
    {
        $errors = $results->reject(fn (ImportRowResult $r) => $r->success)->values();

        return new self(
            importedCount: $results->count() - $errors->count(),
            errorCount: $errors->count(),
            errors: $errors,
        );
    }
}
