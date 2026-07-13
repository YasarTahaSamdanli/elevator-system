<?php

namespace App\Services\InspectionImport;

use Carbon\CarbonImmutable;

/**
 * One PDF attachment lifted out of an inbox message, decoupled from the
 * IMAP library so the import service and its tests never touch a mailbox.
 */
final class IncomingReportMail
{
    public function __construct(
        public readonly ?string $messageId,
        public readonly ?string $from,
        public readonly ?string $subject,
        public readonly ?CarbonImmutable $receivedAt,
        public readonly string $filename,
        public readonly string $pdfContents,
    ) {}
}
