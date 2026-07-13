<?php

namespace App\Services\InspectionImport;

use Carbon\CarbonImmutable;

/**
 * One inbox message with its PDF attachments, decoupled from the IMAP
 * library so the fetch command can be tested with a fake fetcher.
 */
final class FetchedMailMessage
{
    /**
     * @param  list<array{filename: string, contents: string}>  $pdfAttachments
     */
    public function __construct(
        public readonly string $uid,
        public readonly ?string $messageId,
        public readonly ?string $from,
        public readonly ?string $subject,
        public readonly ?CarbonImmutable $receivedAt,
        public readonly array $pdfAttachments,
    ) {}

    public function toIncomingReportMail(int $attachmentIndex): IncomingReportMail
    {
        $attachment = $this->pdfAttachments[$attachmentIndex];

        return new IncomingReportMail(
            messageId: $this->messageId,
            from: $this->from,
            subject: $this->subject,
            receivedAt: $this->receivedAt,
            filename: $attachment['filename'],
            pdfContents: $attachment['contents'],
        );
    }
}
