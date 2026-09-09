<?php

namespace App\Modules\TimeAttendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TimeAttendance\Contracts\TimeClockImportServiceContract;
use App\Modules\TimeAttendance\Http\Requests\CommitImportRequest;
use App\Modules\TimeAttendance\Http\Requests\PreviewImportRequest;
use App\Modules\TimeAttendance\Http\Resources\ImportSummaryResource;

class TimeClockImportController extends Controller
{
    public function __construct(private readonly TimeClockImportServiceContract $imports)
    {
    }

    public function preview(PreviewImportRequest $request)
    {
        $result = $this->imports->preview($request->string('shape')->toString(), $request->file('file'));

        return response()->json($result);
    }

    public function commit(CommitImportRequest $request): ImportSummaryResource
    {
        $summary = $this->imports->commit(
            $request->string('import_token')->toString(),
            $request->string('shape')->toString(),
            $request->toMappingDto(),
        );

        return new ImportSummaryResource($summary);
    }
}
