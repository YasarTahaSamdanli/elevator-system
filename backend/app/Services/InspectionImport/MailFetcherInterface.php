<?php

namespace App\Services\InspectionImport;

interface MailFetcherInterface
{
    /**
     * Fetch unprocessed report mails (unseen, from an allowed sender, with
     * at least one PDF attachment).
     *
     * @return list<FetchedMailMessage>
     */
    public function fetchUnprocessed(): array;

    /**
     * Mark a message as handled so the next run skips it. Called only after
     * every attachment was persisted — a crash in between just means the
     * message is fetched again, which the checksum dedupe makes harmless.
     */
    public function markProcessed(FetchedMailMessage $message): void;
}
