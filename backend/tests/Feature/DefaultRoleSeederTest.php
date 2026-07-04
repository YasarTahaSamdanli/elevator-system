<?php

namespace Tests\Feature;

use Database\Seeders\DefaultRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DefaultRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const DEFAULT_ROLES = [
        'Super Admin',
        'Company Owner',
        'Manager',
        'Technician',
        'Office Staff',
        'Customer',
    ];

    public function test_default_roles_are_created_successfully(): void
    {
        $this->seed(DefaultRoleSeeder::class);

        foreach (self::DEFAULT_ROLES as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        $this->assertSame(count(self::DEFAULT_ROLES), Role::count());
    }

    public function test_default_roles_are_not_duplicated_when_seeded_again(): void
    {
        $this->seed(DefaultRoleSeeder::class);
        $this->seed(DefaultRoleSeeder::class);

        foreach (self::DEFAULT_ROLES as $role) {
            $this->assertSame(1, Role::where('name', $role)->where('guard_name', 'web')->count());
        }

        $this->assertSame(count(self::DEFAULT_ROLES), Role::count());
    }
}
