<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'material' => [
                'uuid' => $this->material?->uuid,
                'code' => $this->material?->code,
                'name' => $this->material?->name,
                'unit' => $this->material?->unit,
            ],
            'warehouse' => [
                'uuid' => $this->warehouse?->uuid,
                'name' => $this->warehouse?->name,
                'type' => $this->warehouse?->type,
            ],
            'type' => $this->type,
            'quantity' => $this->quantity,
            'signed_quantity' => $this->signedQuantity(),
            'unit_price' => $this->unit_price,
            'work_order' => $this->when($this->work_order_id !== null, fn () => [
                'uuid' => $this->workOrder?->uuid,
                'work_order_number' => $this->workOrder?->work_order_number,
            ]),
            'transfer_group_uuid' => $this->transfer_group_uuid,
            'occurred_at' => $this->occurred_at,
            'created_by' => $this->when($this->created_by !== null, fn () => [
                'uuid' => $this->creator?->uuid,
                'name' => $this->creator?->name,
            ]),
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
