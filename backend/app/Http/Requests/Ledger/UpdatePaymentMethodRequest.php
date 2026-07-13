<?php

namespace App\Http\Requests\Ledger;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
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
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $this->route('payment_method');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('payment_methods', 'name')
                    ->where('company_id', Auth::user()?->company_id)
                    ->whereNull('deleted_at')
                    ->ignore($paymentMethod->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
