<?php

namespace App\Http\Resources;

use App\Models\InspectionFinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionFinding */
class InspectionFindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'description' => $this->description,
            'is_resolved' => $this->is_resolved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
