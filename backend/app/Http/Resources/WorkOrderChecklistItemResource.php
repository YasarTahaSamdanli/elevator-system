<?php

namespace App\Http\Resources;

use App\Models\WorkOrderChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkOrderChecklistItem */
class WorkOrderChecklistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'position' => $this->position,
            'label' => $this->label,
            'is_done' => $this->is_done,
            'note' => $this->note,
            'updated_at' => $this->updated_at,
        ];
    }
}
