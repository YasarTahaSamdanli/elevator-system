<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_be_created(): void
    {
        $company = Company::factory()->create([
            'name' => 'Asansor Bakim A.S.',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Asansor Bakim A.S.',
            'is_active' => true,
        ]);
    }

    public function test_company_uuid_is_generated_automatically(): void
    {
        $company = Company::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotEmpty($company->uuid);
        $this->assertTrue(Str::isUuid($company->uuid));
    }
}
