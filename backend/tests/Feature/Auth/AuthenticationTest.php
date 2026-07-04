<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => ['token'],
                'meta',
            ]);

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'USER_INACTIVE');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'deleted@example.com',
            'password' => 'secret-password',
        ]);
        $user->delete();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        $response = $this
            ->withToken($plainTextToken)
            ->postJson('/api/v1/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_authenticated_user_can_get_me(): void
    {
        $company = Company::factory()->create([
            'name' => 'Tenant Company',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
        Role::create(['name' => 'Manager']);
        $user->assignRole('Manager');
        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        $response = $this
            ->withToken($plainTextToken)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.company.uuid', $company->uuid)
            ->assertJsonPath('data.company.name', 'Tenant Company')
            ->assertJsonPath('data.roles.0', 'Manager');
    }
}
