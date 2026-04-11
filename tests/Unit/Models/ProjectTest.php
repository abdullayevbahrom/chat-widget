<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::setBypass(true);
    }

    #[Test]
    public function it_generates_widget_key_with_hash(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $plaintextKey = $project->generateWidgetKey();

        $this->assertStringStartsWith('wsk_', $plaintextKey);
        $this->assertEquals(36, strlen($plaintextKey)); // wsk_ + 32 hex chars
        $this->assertNotNull($project->fresh()->widget_key_hash);
        $this->assertEquals(64, strlen($project->fresh()->widget_key_hash)); // sha256
        $this->assertNotNull($project->fresh()->widget_key_prefix);
    }

    #[Test]
    public function it_sanitizes_settings_with_css_sanitization(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $project->settings = [
            'widget' => [
                'theme' => 'dark',
                'custom_css' => '<script>alert("xss")</script>body { color: red; }',
            ],
        ];
        $project->save();

        $this->assertStringNotContainsString('<script>', $project->fresh()->settings['widget']['custom_css']);
        $this->assertStringContainsString('color: red', $project->fresh()->settings['widget']['custom_css']);
    }

    #[Test]
    public function it_rejects_invalid_widget_theme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget "theme" must be one of: light, dark, auto.');

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $project->settings = ['widget' => ['theme' => 'invalid']];
    }

    #[Test]
    public function it_rejects_invalid_widget_position(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $project->settings = ['widget' => ['position' => 'center']];
    }

    #[Test]
    public function it_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $project->tenant);
        $this->assertEquals($tenant->id, $project->tenant->id);
    }

    #[Test]
    public function it_has_domains_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        \App\Models\ProjectDomain::factory()->count(3)->create(['project_id' => $project->id]);

        $this->assertEquals(3, $project->domains()->count());
    }

    #[Test]
    public function it_regenerates_widget_key(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $oldKey = $project->generateWidgetKey();
        $oldHash = $project->widget_key_hash;

        $newKey = $project->regenerateWidgetKey();

        $this->assertNotEquals($oldKey, $newKey);
        $this->assertNotEquals($oldHash, $project->widget_key_hash);
    }

    #[Test]
    public function it_revokes_widget_key(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $project->generateWidgetKey();

        $project->revokeWidgetKey();

        $this->assertNull($project->fresh()->widget_key_hash);
        $this->assertNull($project->fresh()->widget_key_prefix);
        $this->assertNull($project->fresh()->widget_key_generated_at);
    }

    #[Test]
    public function it_applies_tenant_scope(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        Project::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'Project 1']);
        Project::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'Project 2']);

        Tenant::setCurrent($tenant1);

        // Without global scopes, both should exist
        $allProjects = Project::withoutGlobalScopes()->count();
        $this->assertEquals(2, $allProjects);
    }

    #[Test]
    public function it_checks_if_project_has_widget_key(): void
    {
        $tenant = Tenant::factory()->create();
        $projectWithoutKey = Project::factory()->create(['tenant_id' => $tenant->id]);
        $projectWithKey = Project::factory()->create(['tenant_id' => $tenant->id]);
        $projectWithKey->generateWidgetKey();

        $this->assertFalse($projectWithoutKey->hasWidgetKey());
        $this->assertTrue($projectWithKey->hasWidgetKey());
    }

    #[Test]
    public function it_gets_widget_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'dark', 'primary_color' => '#3B82F6']],
        ]);

        $this->assertEquals('dark', $project->getWidgetSetting('theme'));
        $this->assertEquals('#3B82F6', $project->getWidgetSetting('primary_color'));
        $this->assertNull($project->getWidgetSetting('nonexistent'));
        $this->assertEquals('default', $project->getWidgetSetting('nonexistent', 'default'));
    }

    #[Test]
    public function it_has_verified_domains(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        \App\Models\ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://verified.com',
            'verification_status' => 'verified',
            'is_active' => true,
        ]);
        \App\Models\ProjectDomain::factory()->create([
            'project_id' => $project->id,
            'domain' => 'https://pending.com',
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        $this->assertTrue($project->hasVerifiedDomain());
        $this->assertEquals(1, $project->verifiedDomains()->count());
    }

    #[Test]
    public function it_has_active_conversations(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = \App\Models\Visitor::factory()->create(['tenant_id' => $tenant->id]);

        \App\Models\Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => 'open',
        ]);

        $this->assertTrue($project->hasActiveConversations());
    }

    #[Test]
    public function it_clears_verified_domains_cache(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        Cache::shouldReceive('forget')->once();

        $project->clearVerifiedDomainsCache();
    }

    #[Test]
    public function it_validates_widget_width_range(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        // Valid width
        $project->settings = ['widget' => ['width' => 400]];
        $project->save();
        $this->assertEquals(400, $project->fresh()->settings['widget']['width']);

        // Invalid width
        $this->expectException(\InvalidArgumentException::class);
        $project->settings = ['widget' => ['width' => 100]];
    }

    #[Test]
    public function it_validates_widget_height_range(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(\InvalidArgumentException::class);
        $project->settings = ['widget' => ['height' => 5000]];
    }

    #[Test]
    public function it_validates_primary_color_hex_format(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $project->settings = ['widget' => ['primary_color' => '#3B82F6']];
        $project->save();

        $this->expectException(\InvalidArgumentException::class);
        $project->settings = ['widget' => ['primary_color' => 'not-a-color']];
        $project->save();
    }

    #[Test]
    public function it_rejects_invalid_top_level_settings_keys(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid settings key: 'invalid'. Allowed keys: widget");

        $project->settings = ['invalid' => 'value'];
    }
}
