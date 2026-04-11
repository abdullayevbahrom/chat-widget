<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\Tenant;
use App\Services\WidgetKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WidgetKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WidgetKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WidgetKeyService::class);
    }

    #[Test]
    public function it_validates_valid_widget_key(): void
    {
        $this->assertTrue($this->service->isValidKeyFormat('wsk_'.str_repeat('a', 32)));
    }

    #[Test]
    public function it_rejects_invalid_widget_key_format(): void
    {
        $this->assertFalse($this->service->isValidKeyFormat('invalid-key'));
        $this->assertFalse($this->service->isValidKeyFormat('wsk_short'));
        $this->assertFalse($this->service->isValidKeyFormat('wsk_'.str_repeat('z', 32))); // non-hex
        $this->assertFalse($this->service->isValidKeyFormat(''));
    }

    #[Test]
    public function it_validates_key_and_returns_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $key = $project->generateWidgetKey();

        $result = $this->service->validateKey($key);

        $this->assertNotNull($result);
        $this->assertEquals($project->id, $result->id);
    }

    #[Test]
    public function it_returns_null_for_invalid_key(): void
    {
        $result = $this->service->validateKey('wsk_'.str_repeat('a', 32));

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_nonexistent_key(): void
    {
        $result = $this->service->validateKey('invalid');

        $this->assertNull($result);
    }

    #[Test]
    public function it_caches_validated_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $key = $project->generateWidgetKey();
        $hash = hash('sha256', $key);

        // First call
        $this->service->validateKey($key);

        // Check cache was set
        $cached = Cache::get("project:key:{$hash}");
        $this->assertNotNull($cached);
    }

    #[Test]
    public function it_generates_key_for_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);

        $key = $this->service->generateKey($project);

        $this->assertStringStartsWith('wsk_', $key);
        $this->assertTrue($project->fresh()->hasWidgetKey());
    }

    #[Test]
    public function it_revokes_key_for_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $project->generateWidgetKey();

        $this->service->revokeKey($project);

        $this->assertFalse($project->fresh()->hasWidgetKey());
    }

    #[Test]
    public function it_regenerates_key_for_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $oldKey = $project->generateWidgetKey();

        $newKey = $this->service->regenerateKey($project);

        $this->assertNotEquals($oldKey, $newKey);
        $this->assertTrue($project->fresh()->hasWidgetKey());
    }

    #[Test]
    public function it_clears_project_key_cache(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $project->generateWidgetKey();

        $this->service->clearProjectKeyCache($project);

        // Cache should be cleared
        $this->assertNull(Cache::get("tenant:{$project->tenant_id}:project:key:{$project->widget_key_hash}"));
    }

    #[Test]
    public function it_does_not_validate_inactive_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $key = $project->generateWidgetKey();

        $result = $this->service->validateKey($key);

        $this->assertNull($result);
    }
}
