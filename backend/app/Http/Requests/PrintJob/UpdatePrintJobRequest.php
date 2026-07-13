<?php

namespace App\Http\Requests\PrintJob;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrintJobRequest extends FormRequest
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
            'status' => ['required', Rule::in(['printing', 'done', 'failed'])],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
