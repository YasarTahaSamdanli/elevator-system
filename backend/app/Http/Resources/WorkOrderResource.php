<?php

namespace App\Http\Resources;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkOrder */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'work_order_number' => $this->work_order_number,
            'service_contract' => [
                'uuid' => $this->serviceContract?->uuid,
                'contract_number' => $this->serviceContract?->contract_number,
            ],
            'elevator' => [
                'uuid' => $this->serviceContract?->elevator?->uuid,
                'serial_number' => $this->serviceContract?->elevator?->serial_number,
                'name' => $this->serviceContract?->elevator?->name,
            ],
            'building' => [
                'uuid' => $this->serviceContract?->elevator?->building?->uuid,
                'name' => $this->serviceContract?->elevator?->building?->name,
            ],
            'assigned_user' => $this->when($this->assigned_user_id !== null, fn () => [
                'uuid' => $this->assignedUser?->uuid,
                'name' => $this->assignedUser?->name,
            ]),
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'description' => $this->description,
            'notes' => $this->notes,
            'checklist' => WorkOrderChecklistItemResource::collection($this->whenLoaded('checklistItems')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
