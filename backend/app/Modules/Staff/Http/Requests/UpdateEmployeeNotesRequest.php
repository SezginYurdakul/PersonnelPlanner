<?php

namespace App\Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeNotesRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function notes(): ?string
    {
        return $this->filled('notes') ? $this->string('notes')->toString() : null;
    }
}
