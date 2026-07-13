<?php

namespace App\Http\Requests\InspectionImport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MatchInspectionImportRequest extends FormRequest
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
        ];
    }
}
