<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DefaultRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultRoleSeeder::class);

        $this->company = Company::factory()->create();
        $this->actor = User::factory()->create(['company_id' => $this->company->id]);
        $this->actor->syncRoles(['Company Owner']);
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_their_companys_users(): void
    {
        $otherCompany = Company::factory()->create();

        User::factory()->count(2)->create(['company_id' => $this->company->id]);
        User::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->actor)->getJson('/api/v1/users');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data') // actor + 2 teammates
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $technician = User::factory()->create(['company_id' => $this->company->id]);
        $technician->syncRoles(['Technician']);

        $office = User::factory()->create(['company_id' => $this->company->id]);
        $office->syncRoles(['Office Staff']);

        $response = $this->actingAs($this->actor)
            ->getJson('/api/v1/users?filter[role]=Technician');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $technician->uuid)
            ->assertJsonPath('data.0.roles.0', 'Technician');
    }

    public function test_authenticated_user_can_view_a_single_user(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Mehmet Kaya',
        ]);
        $user->syncRoles(['Technician']);

        $response = $this->actingAs($this->actor)->getJson("/api/v1/users/{$user->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.name', 'Mehmet Kaya')
            ->assertJsonPath('data.roles.0', 'Technician');
    }

    public function test_viewing_another_companys_user_returns_not_found(): void
    {
        $otherUser = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAs($this->actor)
            ->getJson("/api/v1/users/{$otherUser->uuid}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_a_user(): void
    {
        $response = $this->actingAs($this->actor)->postJson('/api/v1/users', [
            'name' => 'Ayse Demir',
            'email' => 'ayse.demir@example.com',
            'phone' => '+90 555 111 22 33',
            'password' => 'super-secret-1',
            'role' => 'Technician',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ayse Demir')
            ->assertJsonPath('data.roles.0', 'Technician');

        $created = User::query()->where('email', 'ayse.demir@example.com')->firstOrFail();

        $this->assertSame($this->company->id, $created->company_id);
        $this->assertTrue(Hash::check('super-secret-1', $created->password));
        $this->assertStringNotContainsString('password', $response->getContent() ?: '');
    }

    public function test_creating_a_user_ignores_client_supplied_company_id(): void
    {
        $otherCompany = Company::factory()->create();

        $this->actingAs($this->actor)->postJson('/api/v1/users', [
            'company_id' => $otherCompany->id,
            'name' => 'Sizinti Deneme',
            'email' => 'sizinti@example.com',
            'password' => 'super-secret-1',
            'role' => 'Technician',
        ])->assertCreated();

        $created = User::query()->where('email', 'sizinti@example.com')->firstOrFail();

        $this->assertSame($this->company->id, $created->company_id);
    }

    public function test_creating_a_user_requires_valid_fields(): void
    {
        $response = $this->actingAs($this->actor)->postJson('/api/v1/users', [
            'email' => 'not-an-email',
            'password' => 'short',
            'role' => 'Nonexistent Role',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['name', 'email', 'password', 'role']]]);
    }

    public function test_creating_a_user_with_duplicate_email_fails(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->actor)->postJson('/api/v1/users', [
            'name' => 'Kopya Kullanici',
            'email' => 'taken@example.com',
            'password' => 'super-secret-1',
            'role' => 'Technician',
        ])->assertStatus(422);
    }

    public function test_authenticated_user_can_update_a_user(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->syncRoles(['Technician']);

        $response = $this->actingAs($this->actor)->putJson("/api/v1/users/{$user->uuid}", [
            'name' => 'Yeni Isim',
            'role' => 'Manager',
            'is_active' => false,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Yeni Isim')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.roles.0', 'Manager');

        $this->assertTrue($user->fresh()->hasRole('Manager'));
        $this->assertFalse($user->fresh()->hasRole('Technician'));
    }

    public function test_updating_password_rehashes_it(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->actor)->putJson("/api/v1/users/{$user->uuid}", [
            'password' => 'brand-new-secret',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-secret', $user->fresh()->password));
    }

    public function test_updating_another_companys_user_returns_not_found(): void
    {
        $otherUser = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAs($this->actor)
            ->putJson("/api/v1/users/{$otherUser->uuid}", ['name' => 'Hacklendi'])
            ->assertNotFound();
    }

    public function test_authenticated_user_can_delete_a_user(): void
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->createToken('api-token');

        $this->actingAs($this->actor)
            ->deleteJson("/api/v1/users/{$user->uuid}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $response = $this->actingAs($this->actor)
            ->deleteJson("/api/v1/users/{$this->actor->uuid}");

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CANNOT_DELETE_SELF');

        $this->assertNull($this->actor->fresh()->deleted_at);
    }

    public function test_deleting_another_companys_user_returns_not_found(): void
    {
        $otherUser = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->actingAs($this->actor)
            ->deleteJson("/api/v1/users/{$otherUser->uuid}")
            ->assertNotFound();
    }
}
