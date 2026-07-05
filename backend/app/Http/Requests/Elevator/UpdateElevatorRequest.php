<?php

namespace App\Http\Requests\Elevator;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateElevatorRequest extends FormRequest
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
            'building_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('buildings', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'serial_number' => ['sometimes', 'required', 'string', 'max:100'],
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:150'],
            'model' => ['sometimes', 'nullable', 'string', 'max:150'],
            'installation_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'capacity_kg' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'person_capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'stop_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'maintenance', 'out_of_service'])],
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
