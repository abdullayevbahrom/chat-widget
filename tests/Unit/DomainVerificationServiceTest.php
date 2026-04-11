<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectDomain;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DomainVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[Test]
    public function it_initiates_verification_with_token(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->for($project)->create([
            'domain' => 'https://example.com',
            'verification_status' => 'pending',
        ]);

        $service = app(DomainVerificationService::class);
        $token = $service->initiateVerification($domain);

        $this->assertNotNull($token);
        $this->assertEquals(32, strlen($token));
        $this->assertEquals('pending', $domain->fresh()->verification_status);
    }

    #[Test]
    public function it_rejects_localhost_in_dns_verification(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->for($project)->create([
            'domain' => 'http://localhost:3000',
            'verification_status' => 'pending',
            'verification_token' => 'test-token-12345678901234567890',
        ]);

        $service = app(DomainVerificationService::class);
        $result = $service->verifyViaDns($domain);

        $this->assertFalse($result);
        $this->assertEquals('failed', $domain->fresh()->verification_status);
        $this->assertStringContainsString('internal', $domain->fresh()->verification_error);
    }

    #[Test]
    public function it_rejects_internal_hostnames_in_dns_verification(): void
    {
        $project = Project::factory()->create();
        $internalHostnames = [
            'http://internal.local',
            'http://app.corp',
            'http://dev.intranet',
            'http://test.home',
            'http://my.lan',
            'http://service.test',
            'http://example.invalid',
            'http://myhost', // no dots, not an IP
        ];

        $service = app(DomainVerificationService::class);

        foreach ($internalHostnames as $hostname) {
            $domain = ProjectDomain::factory()->for($project)->create([
                'domain' => $hostname,
                'verification_status' => 'pending',
                'verification_token' => 'test-token-12345678901234567890',
            ]);

            $result = $service->verifyViaDns($domain);

            $this->assertFalse($result, "Failed to reject: {$hostname}");
            $this->assertEquals('failed', $domain->fresh()->verification_status);
        }
    }

    #[Test]
    public function it_rejects_private_ip_in_dns_verification(): void
    {
        $project = Project::factory()->create();
        $privateIps = [
            'http://192.168.1.1',
            'http://10.0.0.1',
            'http://172.16.0.1',
            'http://127.0.0.1',
        ];

        $service = app(DomainVerificationService::class);

        foreach ($privateIps as $ip) {
            $domain = ProjectDomain::factory()->for($project)->create([
                'domain' => $ip,
                'verification_status' => 'pending',
                'verification_token' => 'test-token-12345678901234567890',
            ]);

            $result = $service->verifyViaDns($domain);

            $this->assertFalse($result, "Failed to reject private IP: {$ip}");
            $this->assertEquals('failed', $domain->fresh()->verification_status);
        }
    }

    #[Test]
    public function it_rejects_expired_verification_token(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->for($project)->create([
            'domain' => 'https://example.com',
            'verification_status' => 'pending',
            'verification_token' => 'test-token-12345678901234567890',
            'verified_at' => now()->subHours(25), // Token expired (>24h)
        ]);

        $service = app(DomainVerificationService::class);
        $result = $service->verifyViaDns($domain);

        $this->assertFalse($result);
        $this->assertEquals('failed', $domain->fresh()->verification_status);
        $this->assertStringContainsString('expired', $domain->fresh()->verification_error);
    }

    #[Test]
    public function it_rejects_empty_domain(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->for($project)->create([
            'domain' => '',
            'verification_status' => 'pending',
            'verification_token' => 'test-token-12345678901234567890',
        ]);

        $service = app(DomainVerificationService::class);
        $result = $service->verifyViaDns($domain);

        $this->assertFalse($result);
        $this->assertEquals('failed', $domain->fresh()->verification_status);
    }

    #[Test]
    public function verify_method_tries_dns_first_then_http(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->for($project)->create([
            'domain' => 'https://nonexistent-domain-12345.com',
            'verification_status' => 'pending',
            'verification_token' => 'test-token-12345678901234567890',
        ]);

        $service = app(DomainVerificationService::class);
        $result = $service->verify($domain);

        // Both DNS and HTTP should fail for a nonexistent domain
        $this->assertFalse($result);
        $this->assertEquals('failed', $domain->fresh()->verification_status);
    }
}
