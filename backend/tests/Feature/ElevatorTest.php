<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Elevator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ElevatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_elevator_can_be_created(): void
    {
        $building = Building::factory()->create();

        $elevator = Elevator::factory()->create([
            'building_id' => $building->id,
            'serial_number' => 'SN-001',
            'name' => 'A Blok Asansor',
        ]);

        $this->assertDatabaseHas('elevators', [
            'id' => $elevator->id,
            'building_id' => $building->id,
            'serial_number' => 'SN-001',
            'name' => 'A Blok Asansor',
            'status' => 'active',
        ]);
    }

    public function test_elevator_uuid_is_generated_automatically(): void
    {
        $elevator = Elevator::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotEmpty($elevator->uuid);
        $this->assertTrue(Str::isUuid($elevator->uuid));
    }

    public function test_elevator_qr_identifier_is_generated_automatically(): void
    {
        $elevator = Elevator::factory()->create([
            'qr_identifier' => null,
        ]);

        $this->assertNotEmpty($elevator->qr_identifier);
        $this->assertTrue(Str::isUuid($elevator->qr_identifier));
    }

    public function test_elevator_building_relationship_works(): void
    {
        $building = Building::factory()->create([
            'name' => 'Merkez Plaza',
        ]);

        $elevator = Elevator::factory()->create([
            'building_id' => $building->id,
        ]);

        $this->assertTrue($elevator->building->is($building));
        $this->assertSame('Merkez Plaza', $elevator->building->name);
        $this->assertTrue($building->elevators->contains($elevator));
    }

    public function test_qr_identifier_must_be_unique(): void
    {
        $qrIdentifier = (string) Str::uuid();

        Elevator::factory()->create([
            'qr_identifier' => $qrIdentifier,
        ]);

        $this->expectException(QueryException::class);

        Elevator::factory()->create([
            'qr_identifier' => $qrIdentifier,
        ]);
    }

    public function test_same_serial_number_can_be_used_in_different_buildings(): void
    {
        $firstBuilding = Building::factory()->create();
        $secondBuilding = Building::factory()->create();

        Elevator::factory()->create([
            'building_id' => $firstBuilding->id,
            'serial_number' => 'SN-001',
        ]);

        $elevator = Elevator::factory()->create([
            'building_id' => $secondBuilding->id,
            'serial_number' => 'SN-001',
        ]);

        $this->assertDatabaseHas('elevators', [
            'id' => $elevator->id,
            'building_id' => $secondBuilding->id,
            'serial_number' => 'SN-001',
        ]);
    }
}
