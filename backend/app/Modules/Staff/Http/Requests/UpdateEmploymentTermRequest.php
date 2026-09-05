<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\DTOs\EmploymentTermData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateEmploymentTermRequest extends FormRequest
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
            'max_weekly_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'effective_from' => ['required', 'date'],
        ];
    }

    public function toDto(): EmploymentTermData
    {
        return new EmploymentTermData(
            maxWeeklyHours: (int) $this->input('max_weekly_hours'),
            effectiveFrom: Carbon::parse($this->string('effective_from')->toString()),
        );
    }
}
