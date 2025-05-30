<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'rut' => '76123456-7',
            'domain' => 'test-company.crecepyme.com',
        ]);

        // Create a test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@user.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        // Set tenant context for permissions
        setPermissionsTeamId($this->tenant->id);
    }

    /**
     * Create a user with specific role
     */
    protected function createUserWithRole(string $roleName): User
    {
        // Create role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'team_id' => $this->tenant->id,
        ]);

        $user = User::create([
            'name' => "Test {$roleName}",
            'email' => "{$roleName}@test.com",
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * Create a permission if it doesn't exist
     */
    protected function createPermission(string $name): Permission
    {
        return Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    /**
     * Act as authenticated user
     */
    protected function actingAsUser(?User $user = null): self
    {
        $user = $user ?: $this->user;
        return $this->actingAs($user);
    }

    /**
     * Act as authenticated admin
     */
    protected function actingAsAdmin(): self
    {
        $admin = $this->createUserWithRole('admin');
        return $this->actingAs($admin);
    }

    /**
     * Create test data for a specific tenant
     */
    protected function createTestData(): void
    {
        // Override in specific test classes as needed
    }
}
