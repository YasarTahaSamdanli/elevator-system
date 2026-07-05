<?php

namespace App\Http\Requests\ServiceContract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateServiceContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'elevator_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('elevators', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'contract_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'expired', 'suspended', 'terminated'])],
            'monthly_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
