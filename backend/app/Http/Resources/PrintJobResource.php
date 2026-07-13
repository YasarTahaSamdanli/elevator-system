<?php

namespace App\Http\Resources;

use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrintJob */
class PrintJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'claimed_at' => $this->claimed_at,
            'printed_at' => $this->printed_at,
            'error_message' => $this->error_message,
            'inspection_import' => $this->whenLoaded('inspectionImport', fn () => $this->inspectionImport === null ? null : [
                'uuid' => $this->inspectionImport->uuid,
                'mail_subject' => $this->inspectionImport->mail_subject,
                'original_filename' => $this->inspectionImport->original_filename,
            ], null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
