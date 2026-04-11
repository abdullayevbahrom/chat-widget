<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\Tenant;
use App\Services\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantResolverTest extends TestCase
{
    use RefreshDatabase;

    protected TenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(TenantResolver::class);
        
        // Clear tenant context for tests
        Tenant::clearCurrent();
    }

    #[Test]
    public function it_resolves_tenant_from_domain(): void
    {
        config(['domains.regex' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/']);

        $tenant = Tenant::factory()->create(['is_active' => true]);
        \App\Models\TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'example.com',
            'is_active' => true,
        ]);

        // Set tenant context so TenantScope doesn't filter results
        Tenant::setCurrent($tenant);

        $result = $this->resolver->resolveFromDomain('example.com');

        $this->assertNotNull($result);
        $this->assertEquals($tenant->id, $result->id);
    }

    #[Test]
    public function it_returns_null_for_unknown_domain(): void
    {
        config(['domains.regex' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/']);

        $result = $this->resolver->resolveFromDomain('nonexistent-domain.com');

        $this->assertNull($result);
    }

    #[Test]
    public function it_resolves_tenant_from_subdomain(): void
    {
        $tenant = Tenant::factory()->create(['subdomain' => 'acme', 'is_active' => true]);
        Tenant::setCurrent($tenant);

        $result = $this->resolver->resolveFromSubdomain('acme');

        $this->assertNotNull($result);
        $this->assertEquals($tenant->id, $result->id);
    }

    #[Test]
    public function it_resolves_from_domain_or_subdomain(): void
    {
        config([
            'domains.regex' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/',
            'app.base_domain' => 'widget.test',
        ]);

        $tenant = Tenant::factory()->create(['subdomain' => 'acme', 'is_active' => true]);
        \App\Models\TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'custom-domain.com',
            'is_active' => true,
        ]);
        Tenant::setCurrent($tenant);

        // Domain match
        $result1 = $this->resolver->resolve('custom-domain.com');
        $this->assertNotNull($result1);

        // Unknown domain returns null
        $result2 = $this->resolver->resolve('unknown.com');
        $this->assertNull($result2);
    }

    #[Test]
    public function it_caches_domain_resolution(): void
    {
        config(['domains.regex' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/']);

        $tenant = Tenant::factory()->create();
        \App\Models\TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'cached-domain.com',
            'is_active' => true,
        ]);
        Tenant::setCurrent($tenant);

        $this->resolver->resolveFromDomain('cached-domain.com');

        // Check cache was set
        $this->assertNotNull(Cache::get('tenant:domain:cached-domain.com'));
    }

    #[Test]
    public function it_clears_domain_cache(): void
    {
        $tenant = Tenant::factory()->create();
        \App\Models\TenantDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'https://clear-domain.com',
            'is_active' => true,
        ]);

        $this->resolver->resolveFromDomain('clear-domain.com');
        $this->resolver->clearDomainCache('clear-domain.com');

        $this->assertNull(Cache::get('tenant:domain:clear-domain.com'));
    }

    #[Test]
    public function it_clears_subdomain_cache(): void
    {
        $tenant = Tenant::factory()->create(['subdomain' => 'test-sub', 'is_active' => true]);
        $this->resolver->resolveFromSubdomain('test-sub');

        $this->resolver->clearSubdomainCache('test-sub');

        $this->assertNull(Cache::get('tenant:subdomain:test-sub'));
    }

    #[Test]
    public function it_rejects_invalid_domain_format(): void
    {
        config(['domains.regex' => '/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/']);

        $this->assertNull($this->resolver->resolveFromDomain(''));
        $this->assertNull($this->resolver->resolveFromDomain('invalid..domain.com'));
    }
}
