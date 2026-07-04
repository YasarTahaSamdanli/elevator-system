<?php

namespace Tests\Unit;

use App\Models\Building;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_uses_soft_deletes(): void
    {
        $building = Building::factory()->create();

        $building->delete();

        $this->assertSoftDeleted('buildings', [
            'id' => $building->id,
        ]);
    }
}
