<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $visitor->tenant);
        $this->assertEquals($tenant->id, $visitor->tenant->id);
    }

    #[Test]
    public function it_has_user_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_authenticated' => true,
        ]);

        $this->assertInstanceOf(User::class, $visitor->user);
    }

    #[Test]
    public function it_has_conversations_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);

        // Create one open conversation and two closed conversations
        // to avoid the unique constraint on (project_id, visitor_id, open_token)
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'status' => 'open',
        ]);
        Conversation::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);
        Conversation::factory()->archived()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        $this->assertEquals(3, $visitor->conversations()->count());
    }

    #[Test]
    public function it_has_messages_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);
        Message::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenant->id,
            'sender_type' => Visitor::class,
            'sender_id' => $visitor->id,
        ]);

        $this->assertEquals(2, $visitor->messages()->count());
    }

    #[Test]
    public function it_tracks_authenticated_flag(): void
    {
        $authenticated = Visitor::factory()->create(['is_authenticated' => true]);
        $anonymous = Visitor::factory()->create(['is_authenticated' => false]);

        $this->assertTrue($authenticated->is_authenticated);
        $this->assertFalse($anonymous->is_authenticated);
    }

    #[Test]
    public function it_tracks_first_visit_and_last_visit(): void
    {
        $visitor = Visitor::factory()->create([
            'first_visit_at' => now()->subDays(5),
            'last_visit_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $visitor->first_visit_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $visitor->last_visit_at);
        $this->assertTrue($visitor->last_visit_at->gt($visitor->first_visit_at));
    }

    #[Test]
    public function it_tracks_visit_count(): void
    {
        $visitor = Visitor::factory()->create(['visit_count' => 5]);

        $this->assertEquals(5, $visitor->visit_count);
    }

    #[Test]
    public function it_casts_is_authenticated_to_boolean(): void
    {
        $visitor = Visitor::factory()->create(['is_authenticated' => 1]);

        $this->assertIsBool($visitor->is_authenticated);
    }

    #[Test]
    public function it_has_unique_session_id(): void
    {
        $tenant = Tenant::factory()->create();
        $visitor1 = Visitor::factory()->create(['tenant_id' => $tenant->id, 'session_id' => 'session-1']);
        $visitor2 = Visitor::factory()->create(['tenant_id' => $tenant->id, 'session_id' => 'session-2']);

        $this->assertNotEquals($visitor1->session_id, $visitor2->session_id);
    }

    #[Test]
    public function it_can_link_to_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $visitor = Visitor::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'is_authenticated' => true,
        ]);

        $this->assertTrue($visitor->is_authenticated);
        $this->assertEquals($user->id, $visitor->user_id);
    }

    #[Test]
    public function it_has_factory(): void
    {
        $visitor = Visitor::factory()->create();

        $this->assertInstanceOf(Visitor::class, $visitor);
        $this->assertNotNull($visitor->tenant_id);
        $this->assertNotNull($visitor->session_id);
    }
}
