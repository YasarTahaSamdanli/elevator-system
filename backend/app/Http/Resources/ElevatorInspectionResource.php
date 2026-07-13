<?php

namespace App\Http\Resources;

use App\Models\ElevatorInspection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ElevatorInspection */
class ElevatorInspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'elevator' => [
                'uuid' => $this->elevator?->uuid,
                'serial_number' => $this->elevator?->serial_number,
                'name' => $this->elevator?->name,
                'building' => [
                    'uuid' => $this->elevator?->building?->uuid,
                    'name' => $this->elevator?->building?->name,
                ],
            ],
            'type' => $this->type,
            'inspection_body' => $this->inspection_body,
            'inspected_at' => $this->inspected_at?->toDateString(),
            'label' => $this->label,
            'report_number' => $this->report_number,
            'follow_up_due_date' => $this->follow_up_due_date?->toDateString(),
            'next_inspection_date' => $this->next_inspection_date?->toDateString(),
            'work_order' => $this->whenLoaded('workOrder', fn () => $this->workOrder === null ? null : [
                'uuid' => $this->workOrder->uuid,
                'work_order_number' => $this->workOrder->work_order_number,
                'status' => $this->workOrder->status,
            ], null),
            'findings' => InspectionFindingResource::collection($this->whenLoaded('findings')),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
