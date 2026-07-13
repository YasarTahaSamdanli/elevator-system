<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InspectionImportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionImport extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<InspectionImportFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const REVIEW_PARSE_FAILED = 'parse_failed';

    public const REVIEW_NO_TEXT_LAYER = 'no_text_layer';

    public const REVIEW_ELEVATOR_NOT_FOUND = 'elevator_not_found';

    public const REVIEW_MULTIPLE_MATCHES = 'multiple_matches';

    public const REVIEW_DUPLICATE_REPORT = 'duplicate_report';

    /**
     * The attributes that are mass assignable.
     *
     * company_id is deliberately excluded: imports are created from console
     * (no Auth) or upload contexts and the company is always assigned
     * explicitly, never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'status',
        'review_reason',
        'error_message',
        'work_order_error',
        'message_id',
        'mail_from',
        'mail_subject',
        'mail_received_at',
        'pdf_disk',
        'pdf_path',
        'pdf_sha256',
        'original_filename',
        'report_number',
        'parsed_payload',
        'elevator_id',
        'elevator_inspection_id',
        'matched_via',
        'created_by',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ElevatorInspection::class, 'elevator_inspection_id');
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markNeedsReview(string $reason, ?string $detail = null): void
    {
        $this->forceFill([
            'status' => 'needs_review',
            'review_reason' => $reason,
            'error_message' => $detail,
        ])->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parsed_payload' => 'array',
            'mail_received_at' => 'datetime',
        ];
    }
}
