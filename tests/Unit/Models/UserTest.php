<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::setBypass(true);
    }

    #[Test]
    public function it_checks_if_user_is_super_admin(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $regularUser = User::factory()->create(['is_super_admin' => false]);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($regularUser->isSuperAdmin());
    }

    #[Test]
    public function it_checks_if_user_is_tenant_user(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_super_admin' => false,
        ]);
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'is_super_admin' => true,
        ]);

        $this->assertTrue($tenantUser->isTenantUser());
        $this->assertFalse($superAdmin->isTenantUser());
    }

    #[Test]
    public function it_has_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertInstanceOf(Tenant::class, $user->tenant);
        $this->assertEquals($tenant->id, $user->tenant->id);
    }

    #[Test]
    public function it_has_tokens_relationship(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphMany::class,
            $user->tokens()
        );
    }

    #[Test]
    public function it_has_telegram_user_id_field(): void
    {
        $user = User::factory()->create(['telegram_user_id' => '123456789']);

        $this->assertEquals('123456789', $user->telegram_user_id);
    }

    #[Test]
    public function it_casts_is_super_admin_to_boolean(): void
    {
        $user = User::factory()->create(['is_super_admin' => 1]);

        $this->assertIsBool($user->is_super_admin);
    }

    #[Test]
    public function it_casts_email_verified_at_to_datetime(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    #[Test]
    public function it_has_assigned_conversations_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $visitor1 = \App\Models\Visitor::factory()->create(['tenant_id' => $tenant->id]);
        $visitor2 = \App\Models\Visitor::factory()->create(['tenant_id' => $tenant->id]);
        \App\Models\Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor1->id,
            'assigned_to' => $user->id,
        ]);
        \App\Models\Conversation::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'visitor_id' => $visitor2->id,
            'assigned_to' => $user->id,
        ]);

        $this->assertEquals(2, $user->assignedConversations()->count());
    }

    #[Test]
    public function it_has_messages_relationship(): void
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphMany::class,
            $user->messages()
        );
    }

    #[Test]
    public function password_is_hidden_in_array(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    #[Test]
    public function it_has_fillable_attributes(): void
    {
        $user = User::factory()->make([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'tenant_id' => 1,
            'telegram_user_id' => '987654',
        ]);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals(1, $user->tenant->id);
        $this->assertEquals('987654', $user->telegram_user_id);
    }
}
