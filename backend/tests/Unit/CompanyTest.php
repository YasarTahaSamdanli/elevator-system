<?php

namespace Tests\Unit;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_uses_soft_deletes(): void
    {
        $company = Company::factory()->create();

        $company->delete();

        $this->assertSoftDeleted('companies', [
            'id' => $company->id,
        ]);
    }
}
