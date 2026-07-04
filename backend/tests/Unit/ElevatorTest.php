<?php

namespace Tests\Unit;

use App\Models\Elevator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElevatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_elevator_uses_soft_deletes(): void
    {
        $elevator = Elevator::factory()->create();

        $elevator->delete();

        $this->assertSoftDeleted('elevators', [
            'id' => $elevator->id,
        ]);
    }
}
