<?php

namespace App\Http\Resources;

use App\Models\ServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceContract */
class ServiceContractResource extends JsonResource
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
            ],
            'contract_number' => $this->contract_number,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'monthly_fee' => $this->monthly_fee,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
