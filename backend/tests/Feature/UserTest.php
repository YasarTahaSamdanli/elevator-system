<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'company_id' => $company->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'is_active' => true,
        ]);
    }

    public function test_user_uuid_is_generated_automatically(): void
    {
        $user = User::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotEmpty($user->uuid);
        $this->assertTrue(Str::isUuid($user->uuid));
    }

    public function test_user_company_relationship_works(): void
    {
        $company = Company::factory()->create([
            'name' => 'Tenant Company',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->assertTrue($user->company->is($company));
        $this->assertSame('Tenant Company', $user->company->name);
    }
}
