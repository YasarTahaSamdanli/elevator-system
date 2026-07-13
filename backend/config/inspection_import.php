<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inspection report import (RoyalCert mail/PDF pipeline)
    |--------------------------------------------------------------------------
    |
    | Nothing here is hardcoded to an environment: point the IMAP settings at
    | a test mailbox (with allowed_senders set to your own address) to rehearse
    | the whole chain end-to-end, then switch the .env values to the real
    | mailbox and rapor@royalcert.com at go-live — no code change.
    |
    */

    // Single-company deployment: every import lands on this company. Becomes
    // a mailbox→company mapping when multi-tenancy arrives.
    'company_id' => env('INSPECTION_IMPORT_COMPANY_ID'),

    'imap' => [
        'host' => env('IMAP_HOST'),
        'port' => (int) env('IMAP_PORT', 993),
        // 'ssl', 'tls' or null (plaintext — only for legacy servers).
        'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
        'validate_cert' => (bool) env('IMAP_VALIDATE_CERT', true),
        'username' => env('IMAP_USERNAME'),
        'password' => env('IMAP_PASSWORD'),
        'folder' => env('IMAP_FOLDER', 'INBOX'),
        // Processed mails are moved here, never deleted.
        'processed_folder' => env('IMAP_PROCESSED_FOLDER', 'Processed'),
        // Keep polling bounded for large Gmail inboxes.
        'since_days' => (int) env('IMAP_SINCE_DAYS', 7),
        'max_messages' => (int) env('IMAP_MAX_MESSAGES', 25),
    ],

    // Comma-separated sender filter; a mail is picked up when the from
    // address ends with any of these (address or domain suffix).
    'allowed_senders' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INSPECTION_IMPORT_ALLOWED_SENDERS', 'royalcert.com')),
    ))),

    // Filesystem disk that stores the report PDFs (s3-ready).
    'disk' => env('INSPECTION_IMPORT_DISK', 'local'),

    // Labels that trigger an automatic revision work order on import.
    'auto_work_order_labels' => ['yellow', 'red'],

    // Queue a print job for the office print agent after a successful import.
    'auto_print' => (bool) env('INSPECTION_IMPORT_AUTO_PRINT', true),

];
