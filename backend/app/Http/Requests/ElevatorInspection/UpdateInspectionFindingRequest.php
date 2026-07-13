<?php

namespace App\Http\Requests\ElevatorInspection;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInspectionFindingRequest extends FormRequest
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
            'description' => ['sometimes', 'string', 'max:1000'],
            'is_resolved' => ['sometimes', 'boolean'],
        ];
    }
}
