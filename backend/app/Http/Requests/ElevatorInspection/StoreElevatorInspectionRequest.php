<?php

namespace App\Http\Requests\ElevatorInspection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreElevatorInspectionRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in(['periodic', 'follow_up'])],
            'inspection_body' => ['nullable', 'string', 'max:255'],
            'inspected_at' => ['required', 'date'],
            'label' => ['required', Rule::in(['green', 'blue', 'yellow', 'red'])],
            'report_number' => ['nullable', 'string', 'max:100'],
            'follow_up_due_date' => ['nullable', 'date'],
            'next_inspection_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'findings' => ['sometimes', 'array'],
            'findings.*.description' => ['required', 'string', 'max:1000'],
        ];
    }
}
