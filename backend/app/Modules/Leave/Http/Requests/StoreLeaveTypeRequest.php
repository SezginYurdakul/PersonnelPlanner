<?php

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\DTOs\LeaveTypeData;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
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
            'requires_approval' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): LeaveTypeData
    {
        return new LeaveTypeData(
            name: $this->string('name')->toString(),
            requiresApproval: $this->boolean('requires_approval', true),
        );
    }
}
