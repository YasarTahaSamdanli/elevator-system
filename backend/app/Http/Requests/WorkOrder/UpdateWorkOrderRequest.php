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
            'type' => ['sometimes', Rule::in(['maintenance', 'fault', 'inspection', 'repair'])],
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
            'qr_identifier' => ['sometimes', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.material_uuid' => [
                'required_with:items',
                'uuid',
                Rule::exists('materials', 'uuid')
                    ->where('company_id', Auth::user()?->company_id)
                    ->where('is_active', true),
            ],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.sale_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
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

                return;
            }

            $this->validateElevatorQr($validator, $workOrder, $status);
        });
    }

    /**
     * Technicians must prove presence at the elevator: starting a work
     * order requires scanning the elevator's QR label. Office/manager
     * accounts stay exempt so the back office can intervene when a label
     * is damaged or missing.
     */
    private function validateElevatorQr(Validator $validator, WorkOrder $workOrder, string $status): void
    {
        if ($status !== 'in_progress' || $workOrder->status === 'in_progress') {
            return;
        }

        $user = Auth::user();

        if ($user === null || ! $user->hasRole('Technician')) {
            return;
        }

        $qrIdentifier = $this->input('qr_identifier');

        if (! is_string($qrIdentifier) || $qrIdentifier === '') {
            $validator->errors()->add(
                'qr_identifier',
                'Starting this work order requires scanning the elevator QR code.',
            );

            return;
        }

        $expected = $workOrder->serviceContract?->elevator?->qr_identifier;

        if ($expected === null || ! hash_equals($expected, $qrIdentifier)) {
            $validator->errors()->add(
                'qr_identifier',
                "The scanned QR code does not belong to this work order's elevator.",
            );
        }
    }
}
