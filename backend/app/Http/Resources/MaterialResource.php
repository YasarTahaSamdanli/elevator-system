<?php

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Material */
class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            'category' => $this->category,
            'min_stock_level' => $this->min_stock_level,
            'default_unit_price' => $this->default_unit_price,
            'default_sale_price' => $this->default_sale_price,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'stock_on_hand' => $this->when(isset($this->stock_on_hand), fn () => (string) $this->stock_on_hand),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
