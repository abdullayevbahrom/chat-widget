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
    public function it_returns_null_for_domain_resolution(): void
    {
        $result = $this->resolver->resolve('example.com');
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_unknown_domain(): void
    {
        $result = $this->resolver->resolve('nonexistent-domain.com');
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_subdomain_resolution(): void
    {
        $result = $this->resolver->resolve('acme.widget.test');
        $this->assertNull($result);
    }

    #[Test]
    public function it_resolves_returns_null(): void
    {
        $this->assertNull($this->resolver->resolve('custom-domain.com'));
        $this->assertNull($this->resolver->resolve('unknown.com'));
    }

    #[Test]
    public function it_clears_domain_cache(): void
    {
        Cache::put('tenant:domain:clear-domain.com', 1, 60);
        $this->resolver->clearDomainCache('clear-domain.com');
        $this->assertNull(Cache::get('tenant:domain:clear-domain.com'));
    }

    #[Test]
    public function it_clears_subdomain_cache(): void
    {
        Cache::put('tenant:subdomain:test-sub', 1, 60);
        $this->resolver->clearSubdomainCache('test-sub');
        $this->assertNull(Cache::get('tenant:subdomain:test-sub'));
    }

    #[Test]
    public function it_returns_null_for_invalid_domain_format(): void
    {
        $this->assertNull($this->resolver->resolve(''));
        $this->assertNull($this->resolver->resolve('invalid..domain.com'));
    }
}
