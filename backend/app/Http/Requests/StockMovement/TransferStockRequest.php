<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TransferStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = Auth::user()?->company_id;

        return [
            'material_uuid' => [
                'required',
                'string',
                Rule::exists('materials', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'from_warehouse_uuid' => [
                'required',
                'string',
                'different:to_warehouse_uuid',
                Rule::exists('warehouses', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'to_warehouse_uuid' => [
                'required',
                'string',
                Rule::exists('warehouses', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
