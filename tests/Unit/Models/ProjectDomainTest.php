<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\ProjectDomain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::setBypass(true);
    }

    #[Test]
    public function it_has_status_constants(): void
    {
        $this->assertEquals('pending', ProjectDomain::STATUS_PENDING);
        $this->assertEquals('verified', ProjectDomain::STATUS_VERIFIED);
        $this->assertEquals('failed', ProjectDomain::STATUS_FAILED);
    }

    #[Test]
    public function it_creates_with_pending_status_by_default(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create(['project_id' => $project->id]);

        $this->assertEquals('pending', $domain->verification_status);
    }

    #[Test]
    public function it_normalizes_domain_input(): void
    {
        $project = Project::factory()->create();

        // With protocol
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://Example1.COM',
        ]);
        $this->assertEquals('https://example1.com', $domain->domain);

        // Without protocol - adds https://
        $domain2 = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'example2.com',
        ]);
        $this->assertEquals('https://example2.com', $domain2->domain);

        // With http protocol
        $domain3 = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'http://example3.com:8080',
        ]);
        $this->assertEquals('http://example3.com:8080', $domain3->domain);
    }

    #[Test]
    public function it_rejects_invalid_domain_schemes(): void
    {
        $project = Project::factory()->create();

        $domain = new ProjectDomain();
        $domain->project_id = $project->id;
        $domain->domain = 'ftp://example.com';
        $domain->save();

        // Invalid scheme should result in null domain
        $this->assertNull($domain->domain);
    }

    #[Test]
    public function it_returns_null_for_empty_domain(): void
    {
        $this->assertNull(ProjectDomain::normalizeDomainInput(''));
        $this->assertNull(ProjectDomain::normalizeDomainInput('   '));
        $this->assertNull(ProjectDomain::normalizeDomainInput(null));
    }

    #[Test]
    public function it_has_project_relationship(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://project-rel-domain1.com',
        ]);

        $this->assertInstanceOf(Project::class, $domain->project);
        $this->assertEquals($project->id, $domain->project->id);
    }

    #[Test]
    public function it_generates_verification_token(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://gen-token-domain1.com',
        ]);

        $token = $domain->generateVerificationToken();

        $this->assertEquals(32, strlen($token));
        $this->assertEquals($token, $domain->fresh()->verification_token);
        $this->assertEquals('pending', $domain->fresh()->verification_status);
    }

    #[Test]
    public function it_marks_as_verified(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'verification_status' => 'pending',
        ]);

        $domain->markAsVerified();

        $this->assertEquals('verified', $domain->verification_status);
        $this->assertNotNull($domain->verified_at);
        $this->assertNull($domain->verification_error);
    }

    #[Test]
    public function it_marks_as_failed(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'verification_status' => 'pending',
        ]);

        $domain->markAsFailed('DNS record not found');

        $this->assertEquals('failed', $domain->verification_status);
        $this->assertEquals('DNS record not found', $domain->verification_error);
    }

    #[Test]
    public function it_checks_if_verified(): void
    {
        $project = Project::factory()->create();

        $verifiedDomain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://verified-domain1.com',
            'verification_status' => 'verified',
            'is_active' => true,
        ]);
        $pendingDomain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://pending-domain1.com',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);
        $inactiveVerifiedDomain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://inactive-verified-domain1.com',
            'verification_status' => 'verified',
            'is_active' => false,
        ]);

        $this->assertTrue($verifiedDomain->isVerified());
        $this->assertFalse($pendingDomain->isVerified());
        $this->assertFalse($inactiveVerifiedDomain->isVerified());
    }

    #[Test]
    public function it_checks_verification_token_validity(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://token-domain1.com',
            'verification_token' => 'some-token',
            'updated_at' => now()->subHours(1),
        ]);

        $this->assertTrue($domain->isVerificationTokenValid());

        // Token expired (>24h)
        $domain->update([
            'verification_token' => 'some-token',
            'updated_at' => now()->subHours(25),
        ]);

        $this->assertFalse($domain->fresh()->isVerificationTokenValid());
    }

    #[Test]
    public function it_checks_if_token_is_blank(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://blank-token-domain1.com',
            'verification_token' => null,
        ]);

        $this->assertFalse($domain->isVerificationTokenValid());
    }

    #[Test]
    public function it_gets_host_for_verification(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://host-verification-domain1.com:8080',
        ]);

        $this->assertEquals('host-verification-domain1.com', $domain->getHostForVerification());
    }

    #[Test]
    public function it_exists_for_project(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://exists-domain1.com',
        ]);

        $this->assertTrue(ProjectDomain::existsForProject($project->id, 'https://exists-domain1.com'));
        $this->assertFalse(ProjectDomain::existsForProject($project->id, 'https://other-domain1.com'));
    }

    #[Test]
    public function it_casts_is_active_to_boolean(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://cast-active-domain1.com',
            'is_active' => 1,
        ]);

        $this->assertIsBool($domain->is_active);
        $this->assertTrue($domain->is_active);
    }

    #[Test]
    public function it_casts_verified_at_to_datetime(): void
    {
        $project = Project::factory()->create();
        $domain = ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://cast-verified-domain1.com',
            'verified_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $domain->verified_at);
    }
}
