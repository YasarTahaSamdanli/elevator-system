<?php

namespace App\Http\Resources;

use App\Models\Elevator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Elevator */
class ElevatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'qr_identifier' => $this->qr_identifier,
            'building' => [
                'uuid' => $this->building?->uuid,
                'name' => $this->building?->name,
            ],
            'serial_number' => $this->serial_number,
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'installation_year' => $this->installation_year,
            'capacity_kg' => $this->capacity_kg,
            'person_capacity' => $this->person_capacity,
            'stop_count' => $this->stop_count,
            'registration_number' => $this->registration_number,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
