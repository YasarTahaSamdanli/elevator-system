<?php

namespace App\Http\Resources;

use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Building */
class BuildingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'manager_name' => $this->manager_name,
            'manager_phone' => $this->manager_phone,
            'entrance_code' => $this->entrance_code,
            'access_notes' => $this->access_notes,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'elevator_count' => $this->whenCounted('elevators'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
