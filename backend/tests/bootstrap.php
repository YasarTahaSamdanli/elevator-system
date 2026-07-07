<?php

/*
 * Hermetic-test guard. Inside Docker, backend/.env is injected as real OS
 * environment variables (compose env_file). PHPUnit's <env force="true">
 * only updates getenv()/$_ENV — not $_SERVER — and Laravel's env() gives
 * $_SERVER precedence, so without this guard the suite would run against
 * the real Postgres/Redis services and RefreshDatabase would WIPE them.
 * Values here must mirror the <php><env> block in phpunit.xml.
 */
$testEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
];

foreach ($testEnv as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require __DIR__.'/../vendor/autoload.php';
