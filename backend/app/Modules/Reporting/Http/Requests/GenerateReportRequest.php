<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\DTOs\ReportPeriodData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function toPeriod(): ReportPeriodData
    {
        return new ReportPeriodData(
            startDate: Carbon::parse($this->string('start_date')->toString())->startOfDay(),
            endDate: Carbon::parse($this->string('end_date')->toString())->endOfDay(),
        );
    }
}
