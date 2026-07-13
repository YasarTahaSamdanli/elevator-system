<?php

namespace App\Http\Resources;

use App\Models\InspectionImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionImport */
class InspectionImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'source' => $this->source,
            'status' => $this->status,
            'review_reason' => $this->review_reason,
            'error_message' => $this->error_message,
            'work_order_error' => $this->work_order_error,
            'mail_from' => $this->mail_from,
            'mail_subject' => $this->mail_subject,
            'mail_received_at' => $this->mail_received_at,
            'original_filename' => $this->original_filename,
            'report_number' => $this->report_number,
            'parsed_payload' => $this->parsed_payload,
            'matched_via' => $this->matched_via,
            'elevator' => $this->whenLoaded('elevator', fn () => $this->elevator === null ? null : [
                'uuid' => $this->elevator->uuid,
                'serial_number' => $this->elevator->serial_number,
                'name' => $this->elevator->name,
                'building' => [
                    'uuid' => $this->elevator->building?->uuid,
                    'name' => $this->elevator->building?->name,
                ],
            ], null),
            'inspection' => $this->whenLoaded('inspection', fn () => $this->inspection === null ? null : [
                'uuid' => $this->inspection->uuid,
                'label' => $this->inspection->label,
                'inspected_at' => $this->inspection->inspected_at?->toDateString(),
                'work_order' => $this->inspection->workOrder === null ? null : [
                    'uuid' => $this->inspection->workOrder->uuid,
                    'work_order_number' => $this->inspection->workOrder->work_order_number,
                    'status' => $this->inspection->workOrder->status,
                ],
            ], null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
