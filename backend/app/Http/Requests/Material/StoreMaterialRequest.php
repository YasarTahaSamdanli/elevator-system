<?php

namespace App\Http\Requests\Material;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('materials', 'code')->where('company_id', Auth::user()?->company_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', Rule::in(['piece', 'meter', 'kg', 'liter', 'set'])],
            'category' => ['nullable', 'string', 'max:100'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'default_unit_price' => ['nullable', 'numeric', 'min:0'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
