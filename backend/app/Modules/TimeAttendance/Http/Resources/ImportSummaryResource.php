<?php

namespace App\Modules\TimeAttendance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\TimeAttendance\DTOs\ImportSummaryData */
class ImportSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'imported_count' => $this->importedCount,
            'error_count' => $this->errorCount,
            'errors' => $this->errors->map(fn ($e) => [
                'row' => $e->sourceRowNumber,
                'error_code' => $e->errorCode,
                'message' => $e->errorMessage,
            ]),
        ];
    }
}
