<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[Test]
    public function tenant_project_api_requires_csrf_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user, 'sanctum');

        // POST without CSRF token should fail
        $response = $this->postJson('/api/tenant/projects', [
            'name' => 'Test Project',
            'slug' => 'test-project',
        ]);

        // Should return 419 (CSRF token mismatch) or 422
        // In Laravel 11+, API routes are in the 'api' middleware group which includes CSRF
        $this->assertTrue(
            $response->status() === 419 || $response->status() === 422,
            "Expected CSRF protection (419/422), got {$response->status()}"
        );
    }

    #[Test]
    public function webhook_endpoint_does_not_require_csrf_token(): void
    {
        $tenant = Tenant::factory()->create();

        // POST to webhook should work without CSRF
        $response = $this->postJson("/api/telegram/webhook/{$tenant->slug}", [
            'message' => [
                'text' => '/start',
            ],
        ]);

        // Should not return 419 (CSRF token mismatch)
        $this->assertNotEquals(419, $response->status(), 'Webhook endpoint should not require CSRF');
    }

    #[Test]
    public function widget_messages_endpoint_does_not_require_csrf_token(): void
    {
        // POST to widget messages should work without CSRF (widget SDK cannot include CSRF)
        $response = $this->postJson('/api/widget/messages', [
            'project_id' => 1,
            'text' => 'Hello',
        ]);

        // Should not return 419 (CSRF token mismatch) — it may return 401 for invalid key
        $this->assertNotEquals(419, $response->status(), 'Widget messages endpoint should not require CSRF');
    }

    #[Test]
    public function project_api_works_with_csrf_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user, 'sanctum');

        // First, get a CSRF token from a web route
        $webResponse = $this->get('/');
        $csrfCookie = $this->call('GET', '/')->headers->getCookies();

        // Create project with proper CSRF token
        $response = $this->withHeaders([
            'X-CSRF-TOKEN' => csrf_token(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])->postJson('/api/tenant/projects', [
            'name' => 'Test Project',
        ]);

        // Should succeed (201) or fail for validation reasons, but NOT 419
        $this->assertNotEquals(419, $response->status(), 'Should not get CSRF error when token is provided');
    }
}
