<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private const API_LIMIT_PER_MINUTE = 240;

    public function test_api_requests_over_the_limit_return_the_error_contract(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);

        for ($i = 0; $i < self::API_LIMIT_PER_MINUTE; $i++) {
            $this->actingAs($user)->getJson('/api/v1/me')->assertOk();
        }

        $response = $this->actingAs($user)->getJson('/api/v1/me');

        $response
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'TOO_MANY_REQUESTS'],
            ])
            ->assertHeader('Retry-After');
    }

    public function test_rate_limit_is_tracked_per_user(): void
    {
        $company = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $company->id]);
        $userB = User::factory()->create(['company_id' => $company->id]);

        for ($i = 0; $i < self::API_LIMIT_PER_MINUTE; $i++) {
            $this->actingAs($userA)->getJson('/api/v1/me')->assertOk();
        }

        // User A is exhausted, user B is unaffected.
        $this->actingAs($userA)->getJson('/api/v1/me')->assertStatus(429);
        $this->actingAs($userB)->getJson('/api/v1/me')->assertOk();
    }
}
