<?php

namespace Tests\Feature\Database;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChatRelationshipsTest — Eloquent model munosabatlarini tekshiradi.
 */
class ChatRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_project_has_many_conversations(): void
    {
        $project = Project::factory()->create();
        $conversations = Conversation::factory()->count(3)->create(['project_id' => $project->id]);

        $this->assertEquals(3, $project->conversations()->count());
        $this->assertTrue($project->conversations()->first() instanceof Conversation);
    }

    /** @test */
    public function test_conversation_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $conversation = Conversation::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($conversation->project instanceof Project);
        $this->assertEquals($project->id, $conversation->project->id);
    }

    /** @test */
    public function test_conversation_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($conversation->tenant instanceof Tenant);
        $this->assertEquals($tenant->id, $conversation->tenant->id);
    }

    /** @test */
    public function test_conversation_belongs_to_visitor(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $this->assertTrue($conversation->visitor instanceof Visitor);
        $this->assertEquals($visitor->id, $conversation->visitor->id);
    }

    /** @test */
    public function test_conversation_has_many_messages_ordered_by_created_at_asc(): void
    {
        $conversation = Conversation::factory()->create();
        $messages = Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
        ]);

        // Messages should be ordered by created_at ascending
        $orderedIds = $conversation->messages()->pluck('id')->toArray();
        $sortedIds = $messages->sortBy('created_at')->pluck('id')->toArray();

        $this->assertEquals($sortedIds, $orderedIds);
    }

    /** @test */
    public function test_conversation_has_latest_messages_ordered_desc(): void
    {
        $conversation = Conversation::factory()->create();
        $messages = Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
        ]);

        // latestMessages should be ordered by created_at descending
        $orderedIds = $conversation->latestMessages()->pluck('id')->toArray();
        $sortedIds = $messages->sortByDesc('created_at')->pluck('id')->toArray();

        $this->assertEquals($sortedIds, $orderedIds);
    }

    /** @test */
    public function test_visitor_has_many_conversations(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        Conversation::factory()->count(3)->create([
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $this->assertEquals(3, $visitor->conversations()->count());
    }

    /** @test */
    public function test_message_belongs_to_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create(['conversation_id' => $conversation->id]);

        $this->assertTrue($message->conversation instanceof Conversation);
        $this->assertEquals($conversation->id, $message->conversation->id);
    }

    /** @test */
    public function test_message_polymorphic_sender_as_visitor(): void
    {
        $tenant = Tenant::factory()->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
        ]);

        $this->assertTrue($message->sender instanceof Visitor);
        $this->assertEquals($visitor->id, $message->sender->id);
    }

    /** @test */
    public function test_message_polymorphic_sender_as_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => User::class,
            'sender_id' => $user->id,
        ]);

        $this->assertTrue($message->sender instanceof User);
        $this->assertEquals($user->id, $message->sender->id);
    }

    /** @test */
    public function test_message_polymorphic_sender_as_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => Tenant::class,
            'sender_id' => $tenant->id,
        ]);

        $this->assertTrue($message->sender instanceof Tenant);
        $this->assertEquals($tenant->id, $message->sender->id);
    }

    /** @test */
    public function test_visitor_has_morph_many_messages(): void
    {
        $tenant = Tenant::factory()->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
        ]);

        $this->assertEquals(3, $visitor->messages()->count());
    }

    /** @test */
    public function test_conversation_belongs_to_assigned_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertTrue($conversation->assignedUser instanceof User);
        $this->assertEquals($user->id, $conversation->assignedUser->id);
    }

    /** @test */
    public function test_tenant_has_many_conversations(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        Conversation::factory()->count(3)->create([
            'project_id' => $project->id,
        ]);

        $this->assertEquals(3, $tenant->conversations()->count());
    }

    /** @test */
    public function test_tenant_has_morph_many_messages(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        Message::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'sender_type' => Tenant::class,
            'sender_id' => $tenant->id,
        ]);

        $this->assertEquals(2, $tenant->messages()->count());
    }
}
