<?php

namespace App\Services\InspectionImport;

use Carbon\CarbonImmutable;
use Throwable;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * IMAP adapter over webklex/php-imap (pure PHP, no ext-imap). Unlike the
 * old office script, processed mails are never deleted: they are flagged
 * seen and moved to the processed folder.
 */
class ImapMailFetcher implements MailFetcherInterface
{
    private ?Client $client = null;

    /** @var array<string, Message> */
    private array $openMessages = [];

    public function fetchUnprocessed(): array
    {
        $client = $this->connect();
        $folder = $client->getFolderByPath(config('inspection_import.imap.folder', 'INBOX'));

        $fetched = [];

        $query = $folder->messages()
            ->unseen()
            ->since(now()->subDays(max(1, (int) config('inspection_import.imap.since_days', 7))))
            ->leaveUnread()
            ->setFetchOrderDesc()
            ->limit(max(1, (int) config('inspection_import.imap.max_messages', 25)));

        /** @var Message $message */
        foreach ($query->get() as $message) {
            $from = $this->fromAddress($message);

            if (! $this->isAllowedSender($from)) {
                continue;
            }

            $attachments = [];

            foreach ($message->getAttachments() as $attachment) {
                $name = (string) $attachment->getName();

                if (! str_ends_with(mb_strtolower($name), '.pdf')) {
                    continue;
                }

                $attachments[] = ['filename' => $name, 'contents' => (string) $attachment->getContent()];
            }

            if ($attachments === []) {
                continue;
            }

            // Gmail can still add \Seen while attachment bodies are loaded,
            // despite FT_PEEK/leaveUnread. Keep the poll idempotent: only
            // mark/move after the import command confirms persistence.
            $message->unsetFlag('Seen');

            $uid = (string) $message->getUid();
            $this->openMessages[$uid] = $message;

            $fetched[] = new FetchedMailMessage(
                uid: $uid,
                messageId: $this->attributeToString($message->getMessageId()),
                from: $from,
                subject: $this->attributeToString($message->getSubject()),
                receivedAt: $this->receivedAt($message),
                pdfAttachments: $attachments,
            );
        }

        return $fetched;
    }

    public function markProcessed(FetchedMailMessage $message): void
    {
        $imapMessage = $this->openMessages[$message->uid] ?? null;

        if ($imapMessage === null) {
            return;
        }

        $imapMessage->setFlag('Seen');

        $processedFolder = config('inspection_import.imap.processed_folder');

        if ($processedFolder) {
            $this->ensureFolderExists($processedFolder);
            $imapMessage->move($processedFolder);
        }

        unset($this->openMessages[$message->uid]);
    }

    private function connect(): Client
    {
        if ($this->client?->isConnected()) {
            return $this->client;
        }

        $config = config('inspection_import.imap');

        $this->client = (new ClientManager)->make([
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'] ?: false,
            'validate_cert' => $config['validate_cert'],
            'username' => $config['username'],
            'password' => $config['password'],
            'protocol' => 'imap',
            'options' => [
                'fetch_order' => 'desc',
            ],
        ]);

        $this->client->connect();

        return $this->client;
    }

    private function fromAddress(Message $message): ?string
    {
        $from = $message->getFrom();
        $address = $from instanceof Attribute ? $from->first() : $from;

        return $address->mail ?? null;
    }

    private function attributeToString(mixed $value): ?string
    {
        if ($value instanceof Attribute) {
            $value = $value->first();
        }

        $value = trim(self::decodeHeader((string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * Gmail delivers non-ASCII subjects MIME-encoded ("=?UTF-8?Q?...?=");
     * decode them so the building-name identity match sees real text.
     */
    public static function decodeHeader(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return $value;
        }

        return mb_decode_mimeheader($value);
    }

    private function receivedAt(Message $message): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::instance($message->getDate()->toDate());
        } catch (Throwable) {
            return null;
        }
    }

    private function isAllowedSender(?string $from): bool
    {
        if ($from === null) {
            return false;
        }

        $from = mb_strtolower(trim($from));

        foreach (config('inspection_import.allowed_senders', []) as $allowed) {
            if (str_ends_with($from, mb_strtolower($allowed))) {
                return true;
            }
        }

        return false;
    }

    private function ensureFolderExists(string $name): void
    {
        $client = $this->connect();

        if ($client->getFolderByPath($name) === null) {
            $client->createFolder($name);
        }
    }
}
