<?php

namespace Tests\Feature\Api;

use App\Models\ProjectDomain;
use App\Models\Tenant;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenants;

class WidgetWsConnectControllerTest extends TestCase
{
    use RefreshDatabaseWithTenants;

    protected string $verifiedOrigin = 'https://example.com';

    protected string $unverifiedOrigin = 'https://evil.example';

    protected string $widgetKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFixtures();
        $this->project->refresh();

        $this->widgetKey = $this->project->generateWidgetKey();

        $projectDomain = ProjectDomain::factory()->create([
            'project_id' => $this->project->id,
            'domain' => $this->verifiedOrigin,
        ]);
        $projectDomain->markAsVerified();

        Tenant::withoutTenantContext(function (): void {
            $this->project->clearVerifiedDomainsCache();
            $this->project->getVerifiedDomainsCache();
        });
    }

    public function test_ws_connect_returns_401_without_widget_auth_headers(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $this->verifiedOrigin,
        ])->getJson('/api/widget/ws/connect');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid or missing widget key.',
            ]);
    }

    public function test_ws_connect_returns_403_for_unverified_origin(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $this->unverifiedOrigin,
            'X-Widget-Key' => $this->widgetKey,
        ])->getJson('/api/widget/ws/connect');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Widget domain is not authorized.',
            ]);
    }

    public function test_ws_connect_returns_200_for_verified_origin_with_widget_key(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $this->verifiedOrigin,
            'X-Widget-Key' => $this->widgetKey,
        ])->getJson('/api/widget/ws/connect');

        $response->assertOk()
            ->assertJsonStructure([
                'ws_host',
                'ws_port',
                'ws_secure',
                'ws_path',
            ]);
    }
}
