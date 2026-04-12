<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Visitor;
use App\Services\WidgetAntiReplayService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenants;

class WidgetConversationControllerTest extends TestCase
{
    use RefreshDatabaseWithTenants;

    protected Visitor $visitor;
    protected Conversation $conversation;
    protected WidgetAntiReplayService $antiReplayService;
    protected string $testOrigin = 'https://example.com';
    protected string $widgetKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFixtures();

        $this->project = $this->makeProject($this->tenant, ['name' => 'Widget Test Project']);
        // Ensure the project has a valid widget key for the middleware
        $this->project->refresh();
        
        // Generate widget key for the project
        $plaintextKey = 'wsk_' . bin2hex(random_bytes(16));
        $hash = hash('sha256', $plaintextKey);
        $prefix = substr($plaintextKey, 0, 8);
        $this->project->update([
            'widget_key_hash' => $hash,
            'widget_key_prefix' => $prefix,
            'widget_key_generated_at' => now(),
            'domain' => 'example.com',
        ]);
        // Store the plaintext key for test requests
        $this->widgetKey = $plaintextKey;

        $this->visitor = $this->makeVisitor($this->tenant);
        $this->antiReplayService = app(WidgetAntiReplayService::class);

        // Create an open conversation
        $this->conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'visitor_id' => $this->visitor->id,
            'status' => Conversation::STATUS_OPEN,
            'source' => Conversation::SOURCE_WIDGET,
            'subject' => 'Test Conversation',
        ]);
    }

    /**
     * Helper: call the close endpoint with middleware bypassed.
     *
     * We bypass route middleware and
     * ValidateCorsOrigins to focus on testing controller logic.
     * The project is manually injected into the request via app resolving.
     */
    protected function postWidgetClose(array $data = [], array $extraHeaders = []): \Illuminate\Testing\TestResponse
    {
        $defaultHeaders = [
            'Accept' => 'application/json',
            'X-Widget-Key' => $this->widgetKey,
            'Origin' => $this->testOrigin,
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        // Merge extra headers, allowing overrides
        $headers = array_merge($defaultHeaders, $extraHeaders);

        // Register a resolving callback to inject the project attribute
        $project = $this->project;
        $this->app->resolving(\Illuminate\Http\Request::class, function ($request) use ($project) {
            $request->attributes->set('project', $project);
        });

        return $this->withHeaders($headers)
            ->withoutMiddleware()
            ->postJson('/api/widget/conversation/close', $data);
    }

    /**
     * Test: close endpoint returns 400 without X-Requested-With header.
     */
    public function test_close_returns_400_without_requested_with_header(): void
    {
        $token = $this->generateAntiReplayToken();

        $response = $this->postWidgetClose([
            'anti_replay_token' => $token,
        ], [
            'X-Requested-With' => '',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'X-Requested-With header is required.',
                'code' => 'MISSING_REQUESTED_WITH_HEADER',
            ]);
    }

    /**
     * Test: close endpoint returns 400 with empty X-Requested-With header.
     */
    public function test_close_returns_400_with_empty_requested_with_header(): void
    {
        $token = $this->generateAntiReplayToken();

        $response = $this->postWidgetClose([
            'anti_replay_token' => $token,
        ], [
            'X-Requested-With' => '',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'X-Requested-With header is required.',
                'code' => 'MISSING_REQUESTED_WITH_HEADER',
            ]);
    }

    /**
     * Test: close endpoint returns 403 with invalid anti_replay_token.
     */
    public function test_close_returns_403_with_invalid_anti_replay_token(): void
    {
        $response = $this->postWidgetClose([
            'anti_replay_token' => 'invalid-token-value',
        ]);

        // Should fail at anti-replay validation (token not found in cache)
        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid or expired anti-replay token.',
                'code' => 'INVALID_ANTI_REPLAY_TOKEN',
            ]);
    }

    /**
     * Test: close endpoint returns 403 with expired anti_replay_token.
     */
    public function test_close_returns_403_with_expired_anti_replay_token(): void
    {
        // Generate a valid token
        $token = $this->generateAntiReplayToken();

        // Manually remove it from cache to simulate expiration
        $cacheKey = "anti-replay:{$this->project->id}:{$this->visitor->id}:{$token}:{$this->visitor->session_id}";
        Cache::forget($cacheKey);

        $response = $this->postWidgetClose([
            'anti_replay_token' => $token,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid or expired anti-replay token.',
                'code' => 'INVALID_ANTI_REPLAY_TOKEN',
            ]);
    }

    /**
     * Test: close endpoint returns 200 with valid token and headers.
     */
    public function test_close_returns_200_with_valid_token_and_headers(): void
    {
        // Generate a valid anti-replay token
        $token = $this->generateAntiReplayToken();

        // Issue visitor cookie
        $visitorToken = $this->buildVisitorToken($this->project, $this->visitor);

        $response = $this->withCookie('widget_visitor_'.$this->project->id, $visitorToken)
            ->postWidgetClose([
                'anti_replay_token' => $token,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'conversation' => [
                    'id' => $this->conversation->id,
                    'status' => Conversation::STATUS_CLOSED,
                ],
            ]);

        // Verify conversation is actually closed in DB
        $this->conversation->refresh();
        $this->assertEquals(Conversation::STATUS_CLOSED, $this->conversation->status);
    }

    /**
     * Test: close endpoint returns 401 without widget key.
     */
    public function test_close_returns_401_without_project_context(): void
    {
        $response = $this->postJson('/api/widget/conversation/close', [
            'anti_replay_token' => 'some-token',
        ], [
            'Origin' => $this->testOrigin,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid or missing widget key.',
            ]);
    }

    /**
     * Test: close endpoint returns 400 when missing anti_replay_token.
     */
    public function test_close_returns_400_when_missing_anti_replay_token(): void
    {
        $response = $this->postWidgetClose([]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Anti-replay token is required.',
                'code' => 'MISSING_ANTI_REPLAY_TOKEN',
            ]);
    }

    /**
     * Test: close endpoint returns 404 when no open conversation found.
     */
    public function test_close_returns_404_when_no_open_conversation(): void
    {
        // Close the conversation first
        $this->conversation->update(['status' => Conversation::STATUS_CLOSED]);

        $token = $this->generateAntiReplayToken();
        $visitorToken = $this->buildVisitorToken($this->project, $this->visitor);

        $response = $this->withCookie('widget_visitor_'.$this->project->id, $visitorToken)
            ->postWidgetClose([
                'anti_replay_token' => $token,
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'No open conversation found.',
                'code' => 'NO_OPEN_CONVERSATION',
            ]);
    }

    /**
     * Test: close endpoint returns 400 when conversation already closed.
     */
    public function test_close_returns_400_when_conversation_already_closed(): void
    {
        $token = $this->generateAntiReplayToken();
        $visitorToken = $this->buildVisitorToken($this->project, $this->visitor);

        // First close succeeds
        $this->withCookie('widget_visitor_'.$this->project->id, $visitorToken)
            ->postWidgetClose([
                'anti_replay_token' => $token,
            ])->assertStatus(200);

        // Generate a new token for second attempt
        $token2 = $this->generateAntiReplayToken();

        // Second close should fail
        $response = $this->withCookie('widget_visitor_'.$this->project->id, $visitorToken)
            ->postWidgetClose([
                'anti_replay_token' => $token2,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Conversation is already closed or archived.',
                'code' => 'CONVERSATION_NOT_OPEN',
            ]);
    }

    /**
     * Generate a valid anti-replay token for the current visitor.
     */
    protected function generateAntiReplayToken(): string
    {
        return $this->antiReplayService->generateToken(
            $this->project->id,
            $this->visitor->id,
            $this->visitor->session_id
        );
    }

    /**
     * Build an encrypted visitor token.
     */
    protected function buildVisitorToken(Project $project, Visitor $visitor): string
    {
        return \Crypt::encryptString(json_encode([
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'session_id' => $visitor->session_id,
        ], JSON_THROW_ON_ERROR));
    }
}
