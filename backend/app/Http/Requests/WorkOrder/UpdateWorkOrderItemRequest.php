<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
