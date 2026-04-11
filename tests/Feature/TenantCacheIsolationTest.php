<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\TenantCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenants;

class TenantCacheIsolationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    /** @test */
    public function it_stores_cache_with_tenant_prefix(): void
    {
        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->actAsTenant($tenant);

        $service = new TenantCacheService();
        $service->put('test_key', 'test_value');

        $value = $service->get('test_key');
        $this->assertEquals('test_value', $value);

        $this->clearTenantContext();
    }

    /** @test */
    public function cache_is_isolated_between_tenants(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Set cache as tenant A
        $this->actAsTenant($tenantA);
        $serviceA = new TenantCacheService();
        $serviceA->put('shared_key', 'value_from_a');

        $this->clearTenantContext();

        // Set cache as tenant B
        $this->actAsTenant($tenantB);
        $serviceB = new TenantCacheService();
        $serviceB->put('shared_key', 'value_from_b');

        // Tenant B should not see tenant A's value
        $valueB = $serviceB->get('shared_key');
        $this->assertEquals('value_from_b', $valueB);

        $this->clearTenantContext();

        // Switch back to tenant A
        $this->actAsTenant($tenantA);
        $serviceA2 = new TenantCacheService();
        $valueA = $serviceA2->get('shared_key');
        $this->assertEquals('value_from_a', $valueA);

        $this->clearTenantContext();
    }

    /** @test */
    public function it_throws_exception_without_tenant_context(): void
    {
        $this->clearTenantContext();

        $service = new TenantCacheService();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot generate tenant cache key');

        $service->key('test_key');
    }

    /** @test */
    public function it_forgets_only_current_tenant_cache(): void
    {
        $tenantA = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['name' => 'Tenant B', 'slug' => 'tenant-b']);

        // Set cache for both tenants
        $this->actAsTenant($tenantA);
        $serviceA = new TenantCacheService();
        $serviceA->put('key_a', 'value_a');

        $this->clearTenantContext();

        $this->actAsTenant($tenantB);
        $serviceB = new TenantCacheService();
        $serviceB->put('key_b', 'value_b');

        // Forget tenant A's cache
        $this->clearTenantContext();
        $this->actAsTenant($tenantA);
        $serviceA2 = new TenantCacheService();
        $serviceA2->forget('key_a');

        $this->clearTenantContext();

        // Verify tenant B's cache is still intact
        $this->actAsTenant($tenantB);
        $serviceB2 = new TenantCacheService();
        $valueB = $serviceB2->get('key_b');
        $this->assertEquals('value_b', $valueB);

        $this->clearTenantContext();
    }

    /** @test */
    public function it_generates_tenant_prefixed_keys(): void
    {
        $tenant = $this->createTenant(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $this->actAsTenant($tenant);

        $service = new TenantCacheService();
        $key = $service->key('my_custom_key');

        $this->assertEquals("tenant:{$tenant->id}:my_custom_key", $key);

        $this->clearTenantContext();
    }
}
