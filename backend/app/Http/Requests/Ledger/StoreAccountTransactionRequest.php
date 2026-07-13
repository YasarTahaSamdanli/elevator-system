<?php

namespace App\Http\Requests\Ledger;

use App\Models\AccountTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAccountTransactionRequest extends FormRequest
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
        $companyId = Auth::user()?->company_id;

        return [
            'building_uuid' => [
                'required',
                'uuid',
                Rule::exists('buildings', 'uuid')->where('company_id', $companyId),
            ],
            'elevator_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('elevators', 'uuid')->where('company_id', $companyId),
            ],
            'type' => [
                'required',
                Rule::in([...AccountTransaction::CHARGE_TYPES, ...AccountTransaction::CREDIT_TYPES]),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['required', 'date'],
            'work_order_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('work_orders', 'uuid')->where('company_id', $companyId),
            ],
            'payment_method_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('payment_methods', 'uuid')->where('company_id', $companyId),
            ],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
