<?php

namespace App\Modules\TimeAttendance\DTOs;

/**
 * The outcome of importing a single ParsedRow (ProjectPlan.md §13a.4/§13a.5) - a row that
 * fails validation is reported back as a per-row error, never thrown as a request-level
 * exception that would abort the rest of the batch.
 */
final readonly class ImportRowResult
{
    private function __construct(
        public int $sourceRowNumber,
        public bool $success,
        public ?int $entryId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function imported(int $sourceRowNumber, int $entryId): self
    {
        return new self($sourceRowNumber, true, entryId: $entryId);
    }

    public static function error(int $sourceRowNumber, string $errorCode, string $errorMessage): self
    {
        return new self($sourceRowNumber, false, errorCode: $errorCode, errorMessage: $errorMessage);
    }
}
