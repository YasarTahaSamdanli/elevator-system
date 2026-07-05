<?php

namespace App\Http\Requests\Elevator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreElevatorRequest extends FormRequest
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
                'required',
                'uuid',
                Rule::exists('buildings', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'serial_number' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'installation_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'capacity_kg' => ['nullable', 'integer', 'min:1'],
            'person_capacity' => ['nullable', 'integer', 'min:1'],
            'stop_count' => ['nullable', 'integer', 'min:1'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'maintenance', 'out_of_service'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
