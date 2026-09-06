<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\DTOs\LineData;
use Illuminate\Foundation\Http\FormRequest;

class StoreLineRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:lines,code'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): LineData
    {
        return new LineData(
            name: $this->string('name')->toString(),
            code: $this->string('code')->toString(),
            isActive: $this->boolean('is_active', true),
        );
    }
}
