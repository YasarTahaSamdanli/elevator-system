<?php

namespace App\Http\Requests\ElevatorInspection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateElevatorInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An inspection permanently belongs to the elevator it was performed
     * on, so elevator_uuid is not updatable. `findings` always carries the
     * full desired set (replace semantics), not a delta.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['periodic', 'follow_up'])],
            'inspection_body' => ['sometimes', 'nullable', 'string', 'max:255'],
            'inspected_at' => ['sometimes', 'date'],
            'label' => ['sometimes', Rule::in(['green', 'blue', 'yellow', 'red'])],
            'report_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'follow_up_due_date' => ['sometimes', 'nullable', 'date'],
            'next_inspection_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'findings' => ['sometimes', 'array'],
            'findings.*.description' => ['required', 'string', 'max:1000'],
            'findings.*.is_resolved' => ['sometimes', 'boolean'],
        ];
    }
}
