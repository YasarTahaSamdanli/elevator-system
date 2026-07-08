<?php

namespace App\Http\Requests\Material;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Material $material */
        $material = $this->route('material');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('materials', 'code')
                    ->where('company_id', Auth::user()?->company_id)
                    ->ignore($material->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'unit' => ['sometimes', 'required', Rule::in(['piece', 'meter', 'kg', 'liter', 'set'])],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'min_stock_level' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'default_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
