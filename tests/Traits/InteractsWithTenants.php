<?php

namespace Tests\Traits;

use App\Models\Tenant;
use App\Models\User;

/**
 * Helper methods for tenant-related testing.
 */
trait InteractsWithTenants
{
    /**
     * Create a tenant with the given attributes.
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        $defaults = [
            'name' => $this->faker->company,
            'slug' => $this->faker->unique()->slug,
            'is_active' => true,
            'plan' => 'free',
        ];

        return Tenant::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a user that belongs to a tenant.
     */
    protected function createTenantUser(Tenant $tenant, array $attributes = []): User
    {
        $defaults = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'tenant_id' => $tenant->id,
        ];

        return User::create(array_merge($defaults, $attributes));
    }

    /**
     * Create a super admin user.
     */
    protected function createSuperAdmin(array $attributes = []): User
    {
        $defaults = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_super_admin' => true,
        ];

        return User::create(array_merge($defaults, $attributes));
    }

    /**
     * Set the current tenant context.
     */
    protected function actAsTenant(Tenant $tenant): void
    {
        Tenant::setCurrent($tenant);
    }

    /**
     * Clear the current tenant context.
     */
    protected function clearTenantContext(): void
    {
        Tenant::clearCurrent();
    }

    /**
     * Authenticate as a user and set tenant context.
     */
    protected function actingAsTenantUser(User $user): self
    {
        $this->actingAs($user, 'sanctum');

        if ($user->tenant !== null) {
            Tenant::setCurrent($user->tenant);
        }

        return $this;
    }

    /**
     * Authenticate as a super admin.
     */
    protected function actingAsSuperAdmin(User $user): self
    {
        $this->actingAs($user, 'sanctum');

        return $this;
    }
}
