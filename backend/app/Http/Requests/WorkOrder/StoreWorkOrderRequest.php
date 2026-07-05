<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
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
        return [
            'service_contract_uuid' => [
                'required',
                'uuid',
                Rule::exists('service_contracts', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'type' => ['required', Rule::in(['maintenance', 'fault', 'inspection', 'modernization', 'repair'])],
            'status' => ['sometimes', Rule::in(['draft', 'planned', 'assigned', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'critical'])],
            'scheduled_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'assigned_user_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
