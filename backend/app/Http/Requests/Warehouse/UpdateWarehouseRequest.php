<?php

namespace App\Http\Requests\Warehouse;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('warehouses', 'name')
                    ->where('company_id', Auth::user()?->company_id)
                    ->ignore($warehouse->id),
            ],
            'type' => ['sometimes', 'required', Rule::in(['main', 'vehicle'])],
            'user_uuid' => [
                'sometimes',
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
