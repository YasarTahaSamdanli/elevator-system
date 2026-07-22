<?php

namespace Tests\Unit;

use App\Services\InspectionImport\ImapMailFetcher;
use PHPUnit\Framework\TestCase;

class ImapMailFetcherTest extends TestCase
{
    public function test_mime_encoded_headers_are_decoded(): void
    {
        $this->assertSame(
            'ÖRNEK EVLER 3 Asansör Denetim Raporu',
            ImapMailFetcher::decodeHeader('=?UTF-8?Q?=C3=96RNEK_EVLER_3_Asans=C3=B6r_Denetim_Raporu?='),
        );
    }

    public function test_plain_headers_pass_through_unchanged(): void
    {
        $this->assertSame(
            'Fatih Apartmanı Asansör Denetim Raporu',
            ImapMailFetcher::decodeHeader('Fatih Apartmanı Asansör Denetim Raporu'),
        );
    }
}
