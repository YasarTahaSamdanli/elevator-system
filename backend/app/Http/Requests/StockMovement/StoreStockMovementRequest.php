<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
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
            'warehouse_uuid' => [
                'required',
                'string',
                Rule::exists('warehouses', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            // transfer_in/transfer_out are deliberately absent: transfers are
            // only created as an atomic pair via POST stock-movements/transfers.
            'type' => ['required', Rule::in(['purchase_in', 'work_order_return', 'adjustment_in', 'adjustment_out'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'work_order_uuid' => [
                'nullable',
                'string',
                Rule::exists('work_orders', 'uuid')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'occurred_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'update_material_price' => ['sometimes', 'boolean'],
        ];
    }
}
