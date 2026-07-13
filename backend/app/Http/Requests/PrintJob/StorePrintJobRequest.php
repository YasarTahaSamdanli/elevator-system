<?php

namespace App\Http\Requests\PrintJob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StorePrintJobRequest extends FormRequest
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
            'inspection_import_uuid' => [
                'required',
                'uuid',
                Rule::exists('inspection_imports', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
        ];
    }
}
