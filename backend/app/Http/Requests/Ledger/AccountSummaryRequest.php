<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountSummaryRequest extends FormRequest
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
                'sometimes',
                'uuid',
                Rule::exists('buildings', 'uuid')->where('company_id', $companyId),
            ],
            'elevator_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('elevators', 'uuid')->where('company_id', $companyId),
            ],
            'occurred_at_from' => ['sometimes', 'date'],
            'occurred_at_to' => ['sometimes', 'date'],
        ];
    }
}
