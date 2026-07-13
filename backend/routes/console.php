<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ayın 1'inde her aktif sözleşmeye o ayın bakım iş emri açılır (idempotent —
// elle `php artisan work-orders:generate-maintenance` ile de koşulabilir).
Schedule::command('work-orders:generate-maintenance')->monthlyOn(1, '02:30');

// Ayın 1'inde tüm aktif sözleşmelere bakım ücreti tahakkuku (idempotent —
// elle `php artisan ledger:accrue-maintenance` ile de koşulabilir).
Schedule::command('ledger:accrue-maintenance')->monthlyOn(1, '03:00');

// Rapor kutusundaki RoyalCert PDF'lerini içeri alır (idempotent — elle
// `php artisan inspections:fetch-mail` ile de koşulabilir). IMAP ayarı
// yoksa komut hızlıca hata verir, zarar yok.
Schedule::command('inspections:fetch-mail')->everyTenMinutes()->withoutOverlapping();
