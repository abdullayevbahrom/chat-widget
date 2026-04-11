<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Services\TenantCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TenantCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantCacheService::class);
    }

    #[Test]
    public function it_generates_tenant_prefixed_key(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $key = $this->service->key('my_data');

        $this->assertEquals("tenant:{$tenant->id}:my_data", $key);
    }

    #[Test]
    public function it_throws_exception_without_tenant_context(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('no tenant context is set');

        $this->service->key('my_data');
    }

    #[Test]
    public function it_puts_value_with_tenant_prefix(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $result = $this->service->put('test_key', 'test_value');

        $this->assertTrue($result);
        $this->assertEquals('test_value', Cache::get("tenant:{$tenant->id}:test_key"));
    }

    #[Test]
    public function it_gets_value_with_tenant_prefix(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $this->service->put('test_key', 'test_value');

        $result = $this->service->get('test_key');

        $this->assertEquals('test_value', $result);
    }

    #[Test]
    public function it_remembers_value_with_callback(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $result = $this->service->remember('remember_key', 60, function () {
            return 'computed_value';
        });

        $this->assertEquals('computed_value', $result);
        $this->assertEquals('computed_value', Cache::get("tenant:{$tenant->id}:remember_key"));
    }

    #[Test]
    public function it_forgets_value(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $this->service->put('forget_key', 'value');
        $result = $this->service->forget('forget_key');

        $this->assertTrue($result);
        $this->assertNull($this->service->get('forget_key'));
    }

    #[Test]
    public function it_isolates_cache_between_tenants(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        Tenant::setCurrent($tenant1);
        $this->service->put('shared_key', 'tenant1_value');

        Tenant::setCurrent($tenant2);

        $this->assertNull($this->service->get('shared_key'));
    }

    #[Test]
    public function it_returns_default_for_missing_key(): void
    {
        $tenant = Tenant::factory()->create();
        Tenant::setCurrent($tenant);

        $result = $this->service->get('nonexistent', 'default_value');

        $this->assertEquals('default_value', $result);
    }
}
