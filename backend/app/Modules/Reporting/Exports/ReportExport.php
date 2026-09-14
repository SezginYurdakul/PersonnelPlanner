<?php

namespace App\Modules\Reporting\Exports;

use App\Modules\Reporting\DTOs\ReportData;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

/**
 * Excel export (ProjectPlan.md §14.2) - reuses the same Blade view as
 * ReportController::exportPdf() so both formats render from one source rather than
 * duplicating the table layout.
 */
final readonly class ReportExport implements FromView
{
    public function __construct(private ReportData $report) {}

    public function view(): View
    {
        return view('reports.report', [
            'period' => $this->report->period,
            'headcount' => $this->report->headcount,
            'costBreakdown' => $this->report->costBreakdown,
        ]);
    }
}
