<?php

namespace App\Http\Requests\WorkOrder;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
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
            'service_contract_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('service_contracts', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'type' => ['sometimes', Rule::in(['maintenance', 'fault', 'inspection', 'modernization', 'repair'])],
            'status' => ['sometimes', Rule::in(['draft', 'planned', 'assigned', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'critical'])],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'assigned_user_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
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
