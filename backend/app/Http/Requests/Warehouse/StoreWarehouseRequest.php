<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('warehouses', 'name')->where('company_id', Auth::user()?->company_id),
            ],
            'type' => ['required', Rule::in(['main', 'vehicle'])],
            'user_uuid' => [
                'nullable',
                'string',
                Rule::exists('users', 'uuid')->where(fn ($query) => $query->where('company_id', Auth::user()?->company_id)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'main') {
            $this->merge(['user_uuid' => null]);
        }
    }
}
