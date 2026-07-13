<?php

namespace App\Http\Resources;

use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountTransaction */
class AccountTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'building' => [
                'uuid' => $this->building?->uuid,
                'name' => $this->building?->name,
            ],
            'elevator' => $this->elevator === null ? null : [
                'uuid' => $this->elevator->uuid,
                'name' => $this->elevator->name,
                'serial_number' => $this->elevator->serial_number,
            ],
            'type' => $this->type,
            'amount' => $this->amount,
            'signed_amount' => $this->signed_amount,
            'occurred_at' => $this->occurred_at?->toDateString(),
            'work_order' => $this->workOrder === null ? null : [
                'uuid' => $this->workOrder->uuid,
                'work_order_number' => $this->workOrder->work_order_number,
            ],
            'payment_method' => $this->paymentMethod === null ? null : [
                'uuid' => $this->paymentMethod->uuid,
                'name' => $this->paymentMethod->name,
            ],
            'collected_by' => $this->collectedBy === null ? null : [
                'uuid' => $this->collectedBy->uuid,
                'name' => $this->collectedBy->name,
            ],
            'payer_name' => $this->payer_name,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
