<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Contracts\ReportingServiceContract;
use App\Modules\Reporting\DTOs\ReportPeriodData;
use App\Modules\Reporting\Exports\ReportExport;
use App\Modules\Reporting\Http\Requests\GenerateReportRequest;
use App\Modules\Reporting\Http\Resources\ReportResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportingServiceContract $reports) {}

    public function show(GenerateReportRequest $request): ReportResource
    {
        return new ReportResource($this->reports->generate($request->toPeriod()));
    }

    public function exportPdf(GenerateReportRequest $request): Response
    {
        $report = $this->reports->generate($request->toPeriod());

        $pdf = Pdf::loadView('reports.report', [
            'period' => $report->period,
            'headcount' => $report->headcount,
            'costBreakdown' => $report->costBreakdown,
        ]);

        return $pdf->download($this->filename($report->period, 'pdf'));
    }

    public function exportExcel(GenerateReportRequest $request): BinaryFileResponse
    {
        $report = $this->reports->generate($request->toPeriod());

        return Excel::download(new ReportExport($report), $this->filename($report->period, 'xlsx'));
    }

    private function filename(ReportPeriodData $period, string $extension): string
    {
        return "workforce-report_{$period->startDate->toDateString()}_{$period->endDate->toDateString()}.{$extension}";
    }
}
