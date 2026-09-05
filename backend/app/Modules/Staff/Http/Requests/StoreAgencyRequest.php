<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\DTOs\AgencyData;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level `role:admin` middleware already restricts access to this endpoint.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:agencies,code'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): AgencyData
    {
        return new AgencyData(
            name: $this->string('name')->toString(),
            code: $this->string('code')->toString(),
            contactEmail: $this->string('contact_email')->toString() ?: null,
            contactPhone: $this->string('contact_phone')->toString() ?: null,
            isActive: $this->boolean('is_active', true),
        );
    }
}
