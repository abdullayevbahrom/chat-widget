<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenants;

class WidgetWsConnectControllerTest extends TestCase
{
    use RefreshDatabaseWithTenants;

    protected string $verifiedOrigin = 'https://example.com';

    protected string $unverifiedOrigin = 'https://evil.example';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFixtures();
        $this->project->update(['domain' => 'example.com']);
    }

    public function test_ws_connect_returns_200_for_registered_origin(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $this->verifiedOrigin,
        ])->getJson('/api/widget/ws/connect');

        $response->assertOk()
            ->assertJsonStructure([
                'ws_host',
                'ws_port',
                'ws_secure',
                'ws_path',
            ]);
    }

    public function test_ws_connect_returns_400_for_unregistered_origin(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => $this->unverifiedOrigin,
        ])->getJson('/api/widget/ws/connect');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid or unregistered domain. Please add this domain to your project settings.',
            ]);
    }
}
