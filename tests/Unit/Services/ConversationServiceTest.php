<?php

namespace Tests\Unit\Services;

use App\Events\ConversationArchived;
use App\Events\ConversationClosed;
use App\Events\ConversationOpened;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ConversationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConversationService::class);
        
        // Fake broadcasts to avoid Reverb connection errors
        Broadcast::fake();
    }

    #[Test]
    public function it_opens_a_new_conversation(): void
    {
        Event::fake([ConversationOpened::class]);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        $conversation = $this->service->openConversation($visitor, $project);

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals('open', $conversation->status);
        $this->assertEquals($project->id, $conversation->project_id);
        $this->assertEquals($visitor->id, $conversation->visitor_id);

        Event::assertDispatched(ConversationOpened::class);
    }

    #[Test]
    public function it_reuses_existing_open_conversation(): void
    {
        Event::fake([ConversationOpened::class]);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        $conversation1 = $this->service->openConversation($visitor, $project);
        $conversation2 = $this->service->openConversation($visitor, $project);

        $this->assertEquals($conversation1->id, $conversation2->id);
        Event::assertDispatchedTimes(ConversationOpened::class, 1);
    }

    #[Test]
    public function it_closes_a_conversation(): void
    {
        Event::fake([ConversationClosed::class]);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = $this->service->openConversation($visitor, $project);

        $result = $this->service->closeConversation($conversation);

        $this->assertEquals('closed', $result->status);
        $this->assertNotNull($result->closed_at);

        Event::assertDispatched(ConversationClosed::class);

        // Verify system message was created
        $systemMessage = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('message_type', 'system')
            ->first();
        $this->assertNotNull($systemMessage);
    }

    #[Test]
    public function it_archives_a_conversation(): void
    {
        Event::fake([ConversationArchived::class]);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = $this->service->openConversation($visitor, $project);
        $conversation->close();

        $result = $this->service->archiveConversation($conversation);

        $this->assertEquals('archived', $result->status);
        Event::assertDispatched(ConversationArchived::class);
    }

    #[Test]
    public function it_gets_open_conversations_for_project(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor1 = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $visitor2 = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        $this->service->openConversation($visitor1, $project);
        $this->service->openConversation($visitor2, $project);

        Tenant::setCurrent($tenant);

        $conversations = $this->service->getConversationsForProject($project, 'open');

        $this->assertEquals(2, $conversations->total());
    }

    #[Test]
    public function it_assigns_a_conversation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = $this->service->openConversation($visitor, $project);

        $result = $this->service->assignConversation($conversation, $user);

        $this->assertEquals($user->id, $result->assigned_to);
    }

    #[Test]
    public function it_gets_conversation_with_messages(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = $this->service->openConversation($visitor, $project);

        $result = $this->service->getConversationWithMessages($conversation->id);

        $this->assertInstanceOf(Conversation::class, $result['conversation']);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result['messages']);
    }

    #[Test]
    public function it_closes_idle_conversations(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'settings' => ['widget' => ['theme' => 'light']],
        ]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = $this->service->openConversation($visitor, $project);

        // Make the conversation appear idle
        $conversation->update([
            'last_message_at' => Carbon::now()->subDays(2),
        ]);

        $cutoff = Carbon::now()->subDay();
        $closedCount = $this->service->closeConversationsOlderThan($cutoff);

        $this->assertEquals(1, $closedCount);
        $this->assertEquals('closed', $conversation->fresh()->status);
    }
}
