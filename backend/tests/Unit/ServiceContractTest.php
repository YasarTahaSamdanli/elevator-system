<?php

namespace Tests\Unit;

use App\Models\ServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_contract_uses_soft_deletes(): void
    {
        $serviceContract = ServiceContract::factory()->create();

        $serviceContract->delete();

        $this->assertSoftDeleted('service_contracts', [
            'id' => $serviceContract->id,
        ]);
    }
}
