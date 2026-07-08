<?php

namespace App\Http\Resources;

use App\Models\WorkOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkOrderItem */
class WorkOrderItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->unit_price === null ? null : (string) ((float) $this->quantity * (float) $this->unit_price),
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
