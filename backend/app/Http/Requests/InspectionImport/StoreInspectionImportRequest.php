<?php

namespace App\Http\Requests\InspectionImport;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectionImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}
