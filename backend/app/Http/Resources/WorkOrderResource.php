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
            // Saha ekibi iş emri detayından yol tarifi başlatabildiği için
            // bina adresi/erişim bilgileri burada tam olarak taşınır.
            'building' => $this->buildingPayload(),
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
            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildingPayload(): array
    {
        $building = $this->serviceContract?->elevator?->building;

        return [
            'uuid' => $building?->uuid,
            'name' => $building?->name,
            'address' => $building?->address,
            'city' => $building?->city,
            'district' => $building?->district,
            'manager_name' => $building?->manager_name,
            'manager_phone' => $building?->manager_phone,
            'entrance_code' => $building?->entrance_code,
            'access_notes' => $building?->access_notes,
            'latitude' => $building?->latitude,
            'longitude' => $building?->longitude,
        ];
    }
}
