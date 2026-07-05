<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Every API error must follow the ApiResponse::error() contract:
 * {success: false, message, error: {code, details}} — regardless of
 * where the exception originated (validation, route binding, HTTP
 * method mismatch or an unexpected server error).
 */
class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  \Illuminate\Testing\TestResponse<\Illuminate\Http\JsonResponse>  $response
     */
    private function assertErrorShape($response, int $status, string $code): void
    {
        $response
            ->assertStatus($status)
            ->assertJson([
                'success' => false,
                'error' => ['code' => $code],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'error' => ['code', 'details'],
            ]);
    }

    public function test_unauthenticated_request_returns_error_contract(): void
    {
        $response = $this->getJson('/api/v1/buildings');

        $this->assertErrorShape($response, 401, 'UNAUTHENTICATED');
    }

    public function test_validation_failure_returns_error_contract_with_field_details(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $this->assertErrorShape($response, 422, 'VALIDATION_ERROR');

        $response->assertJsonStructure(['error' => ['details' => ['email', 'password']]]);
    }

    public function test_unknown_resource_uuid_returns_error_contract(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/buildings/'.Str::uuid());

        $this->assertErrorShape($response, 404, 'NOT_FOUND');
    }

    public function test_other_companys_resource_returns_not_found_error_contract(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $companyA->id]);
        $buildingB = Building::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/buildings/'.$buildingB->uuid);

        $this->assertErrorShape($response, 404, 'NOT_FOUND');
    }

    public function test_unknown_route_returns_error_contract(): void
    {
        $response = $this->getJson('/api/v1/nonexistent');

        $this->assertErrorShape($response, 404, 'NOT_FOUND');
    }

    public function test_method_not_allowed_returns_error_contract(): void
    {
        $response = $this->getJson('/api/v1/login');

        $this->assertErrorShape($response, 405, 'METHOD_NOT_ALLOWED');
    }

    public function test_unexpected_exception_returns_error_contract(): void
    {
        Route::get('/api/v1/boom', function (): never {
            throw new RuntimeException('kaboom');
        });

        $response = $this->getJson('/api/v1/boom');

        $this->assertErrorShape($response, 500, 'SERVER_ERROR');
    }

    public function test_unexpected_exception_message_is_hidden_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/v1/boom', function (): never {
            throw new RuntimeException('secret internal detail');
        });

        $response = $this->getJson('/api/v1/boom');

        $this->assertErrorShape($response, 500, 'SERVER_ERROR');

        $this->assertStringNotContainsString(
            'secret internal detail',
            $response->getContent() ?: '',
        );
    }
}
