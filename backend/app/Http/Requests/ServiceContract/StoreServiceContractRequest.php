<?php

namespace App\Http\Requests\ServiceContract;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreServiceContractRequest extends FormRequest
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
                'required',
                'uuid',
                Rule::exists('elevators', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'expired', 'suspended', 'terminated'])],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error(
            message: 'Validation failed.',
            code: 'VALIDATION_ERROR',
            details: $validator->errors()->toArray(),
            status: 422,
        ));
    }
}
