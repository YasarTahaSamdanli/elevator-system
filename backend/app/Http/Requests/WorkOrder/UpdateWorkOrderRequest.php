<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkOrderRequest extends FormRequest
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
                'sometimes',
                'uuid',
                Rule::exists('service_contracts', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'type' => ['sometimes', Rule::in(['maintenance', 'fault', 'inspection', 'modernization', 'repair'])],
            'status' => ['sometimes', Rule::in(['draft', 'planned', 'assigned', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'critical'])],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'assigned_user_uuid' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('users', 'uuid')
                    ->where('company_id', Auth::user()?->company_id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Reject status changes that move against the lifecycle
     * (see WorkOrder::STATUS_ORDER).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('status');
            $workOrder = $this->route('work_order');

            if (! is_string($status) || ! $workOrder instanceof WorkOrder) {
                return;
            }

            $known = $status === 'cancelled' || array_key_exists($status, WorkOrder::STATUS_ORDER);

            if ($known && ! $workOrder->canTransitionTo($status)) {
                $validator->errors()->add(
                    'status',
                    "Work order status cannot change from '{$workOrder->status}' to '{$status}'.",
                );
            }
        });
    }
}
