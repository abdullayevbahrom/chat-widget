<?php

namespace Tests\Traits;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Extends RefreshDatabase with common tenant fixtures for testing.
 *
 * Use this trait in feature tests that need database access and
 * want common tenant/project fixtures available without boilerplate.
 */
trait RefreshDatabaseWithTenants
{
    use RefreshDatabase;

    /**
     * Default tenant created for tests.
     */
    protected ?Tenant $tenant = null;

    /**
     * Default user created for tests.
     */
    protected ?User $user = null;

    /**
     * Default project created for tests.
     */
    protected ?Project $project = null;

    /**
     * Create a tenant with the given attributes.
     */
    protected function makeTenant(array $attributes = []): Tenant
    {
        return Tenant::factory()->create($attributes);
    }

    /**
     * Create a tenant user with the given attributes.
     */
    protected function makeTenantUser(?Tenant $tenant = null, array $attributes = []): User
    {
        $tenant ??= $this->tenant ?? $this->makeTenant();

        return User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $attributes));
    }

    /**
     * Create a super admin user.
     */
    protected function makeSuperAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_super_admin' => true,
            'tenant_id' => null,
        ], $attributes));
    }

    /**
     * Create a project for the given tenant.
     */
    protected function makeProject(?Tenant $tenant = null, array $attributes = []): Project
    {
        $tenant ??= $this->tenant ?? $this->makeTenant();

        return Project::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $attributes));
    }

    /**
     * Create a visitor for the given tenant.
     */
    protected function makeVisitor(?Tenant $tenant = null, array $attributes = []): Visitor
    {
        $tenant ??= $this->tenant ?? $this->makeTenant();

        return Visitor::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $attributes));
    }

    /**
     * Set up common test fixtures.
     * Creates a default tenant, user, and project.
     */
    protected function setUpFixtures(): void
    {
        $this->tenant = $this->makeTenant(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $this->user = $this->makeTenantUser($this->tenant, ['name' => 'Test User']);
        $this->project = $this->makeProject($this->tenant, ['name' => 'Test Project']);
    }

    /**
     * Clean up tenant context after each test.
     */
    protected function tearDown(): void
    {
        $this->clearTenantContext();
        $this->tenant = null;
        $this->user = null;
        $this->project = null;

        parent::tearDown();
    }

    /**
     * Act as the current tenant.
     */
    protected function actAsTenant(?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenant;

        if ($tenant !== null) {
            Tenant::setCurrent($tenant);
        }
    }

    /**
     * Clear the current tenant context.
     */
    protected function clearTenantContext(): void
    {
        Tenant::clearCurrent();
    }

    /**
     * Authenticate as the default test user with Sanctum.
     */
    protected function authenticateAsDefaultUser(?User $user = null): void
    {
        $user ??= $this->user;

        if ($user !== null) {
            $this->actingAs($user, 'sanctum');
        }
    }
}
