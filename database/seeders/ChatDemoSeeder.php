<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;

/**
 * ChatDemoSeeder — Development muhiti uchun chat demo ma'lumotlarini yaratadi.
 *
 * Yaratiladigan ma'lumotlar:
 * - 1 ta Tenant
 * - 2 ta Project (widget key + domain bilan)
 * - 5 ta Visitor (2 ta authenticated)
 * - 10 ta Conversation (turli status va source lar)
 * - 50 ta Message (turli sender, direction, message_type lar)
 */
class ChatDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('ChatDemoSeeder should not be run in production.');
        }

        // ── Tenant ──────────────────────────────────────────────────────────
        $tenant = Tenant::create([
            'name' => 'Demo Chat Company',
            'slug' => 'demo-chat',
            'is_active' => true,
            'plan' => 'pro',
            'subscription_expires_at' => now()->addYear(),
        ]);

        // ── Projects ────────────────────────────────────────────────────────
        $projectA = Project::factory()->withWidgetKey()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Website Widget',
            'slug' => 'demo-website',
            'domain' => 'demo-chat.example.com',
            'description' => 'Main website support widget',
            'is_active' => true,
        ]);

        $projectB = Project::factory()->withWidgetKey()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Telegram Bot',
            'slug' => 'demo-telegram',
            'domain' => 'demo-telegram.example.com',
            'description' => 'Telegram integration project',
            'is_active' => true,
        ]);

        // ── Visitors ────────────────────────────────────────────────────────
        $visitors = [];

        // 3 ta oddiy (anonymous) visitor
        for ($i = 0; $i < 3; $i++) {
            $visitors[] = Visitor::factory()->withBrowserInfo()->create([
                'tenant_id' => $tenant->id,
                'session_id' => fake()->uuid(),
            ]);
        }

        // 2 ta authenticated visitor
        for ($i = 0; $i < 2; $i++) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Demo User '.($i + 1),
                'email' => "demo.user.{$i}@demo-chat.test",
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            $visitors[] = Visitor::factory()->withBrowserInfo()->authenticated()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'session_id' => fake()->uuid(),
            ]);
        }

        // ── Conversations ───────────────────────────────────────────────────
        $conversations = [];

        // 6 ta open conversation
        for ($i = 0; $i < 6; $i++) {
            $conversations[] = Conversation::create([
                'tenant_id' => $tenant->id,
                'project_id' => $i < 4 ? $projectA->id : $projectB->id,
                'visitor_id' => $visitors[$i % 5]->id,
                'status' => Conversation::STATUS_OPEN,
                'subject' => fake()->optional(0.5)->sentence(4),
                'source' => $i < 4 ? Conversation::SOURCE_WIDGET : Conversation::SOURCE_TELEGRAM,
                'open_token' => Conversation::OPEN_TOKEN_ACTIVE,
                'metadata' => ['demo' => true, 'sequence' => $i],
            ]);
        }

        // 3 ta closed conversation
        for ($i = 0; $i < 3; $i++) {
            $conversations[] = Conversation::create([
                'tenant_id' => $tenant->id,
                'project_id' => $i < 2 ? $projectA->id : $projectB->id,
                'visitor_id' => $visitors[$i % 5]->id,
                'status' => Conversation::STATUS_CLOSED,
                'subject' => "Resolved: ".fake()->sentence(3),
                'source' => Conversation::SOURCE_WIDGET,
                'open_token' => null,
                'metadata' => ['demo' => true, 'resolved' => true],
            ]);
        }

        // 1 ta archived conversation
        $conversations[] = Conversation::create([
            'tenant_id' => $tenant->id,
            'project_id' => $projectA->id,
            'visitor_id' => $visitors[0]->id,
            'status' => Conversation::STATUS_ARCHIVED,
            'subject' => 'Archived old conversation',
            'source' => Conversation::SOURCE_API,
            'open_token' => null,
            'metadata' => ['demo' => true, 'archived' => true],
        ]);

        // ── Messages ────────────────────────────────────────────────────────
        $messageCount = 0;

        foreach ($conversations as $idx => $conversation) {
            $visitor = $visitors[$idx % 5];
            $numMessages = $idx < 7 ? rand(4, 8) : rand(1, 3);

            for ($m = 0; $m < $numMessages && $messageCount < 50; $m++) {
                $isInbound = $m % 2 === 0;

                if ($isInbound) {
                    // Visitor to'mondan kelgan xabar
                    Message::create([
                        'tenant_id' => $tenant->id,
                        'conversation_id' => $conversation->id,
                        'sender_type' => Visitor::class,
                        'sender_id' => $visitor->id,
                        'message_type' => $m === 3 ? Message::TYPE_IMAGE : Message::TYPE_TEXT,
                        'body' => fake()->sentence(),
                        'direction' => Message::DIRECTION_INBOUND,
                        'is_read' => $conversation->status !== Conversation::STATUS_OPEN,
                        'read_at' => $conversation->status !== Conversation::STATUS_OPEN ? now() : null,
                        'attachments' => $m === 3 ? [['type' => 'image', 'url' => fake()->imageUrl()]] : null,
                        'metadata' => ['demo' => true],
                    ]);
                } else {
                    // Agent/tenant to'mondan kelgan xabar
                    Message::create([
                        'tenant_id' => $tenant->id,
                        'conversation_id' => $conversation->id,
                        'sender_type' => Tenant::class,
                        'sender_id' => $tenant->id,
                        'message_type' => Message::TYPE_TEXT,
                        'body' => fake()->sentence(),
                        'direction' => Message::DIRECTION_OUTBOUND,
                        'is_read' => true,
                        'read_at' => now(),
                        'metadata' => ['demo' => true],
                    ]);
                }

                $messageCount++;
            }

            // Add system/event messages for some conversations
            if ($conversation->status === Conversation::STATUS_CLOSED && $messageCount < 50) {
                Message::create([
                    'tenant_id' => $tenant->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => null,
                    'sender_id' => null,
                    'message_type' => Message::TYPE_SYSTEM,
                    'body' => 'Conversation was closed automatically.',
                    'direction' => Message::DIRECTION_OUTBOUND,
                    'is_read' => true,
                    'read_at' => now(),
                    'metadata' => ['demo' => true, 'system' => true],
                ]);
                $messageCount++;
            }

            if ($conversation->status === Conversation::STATUS_OPEN && $m % 3 === 0 && $messageCount < 50) {
                Message::create([
                    'tenant_id' => $tenant->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => null,
                    'sender_id' => null,
                    'message_type' => Message::TYPE_EVENT,
                    'body' => 'Visitor is typing...',
                    'direction' => Message::DIRECTION_INBOUND,
                    'is_read' => false,
                    'metadata' => ['demo' => true, 'event' => 'typing'],
                ]);
                $messageCount++;
            }
        }

        $this->command->info('ChatDemoSeeder completed!');
        $this->command->info("  Tenant: {$tenant->name}");
        $this->command->info("  Projects: 2 ({$projectA->name}, {$projectB->name})");
        $this->command->info('  Visitors: 5 (2 authenticated)');
        $this->command->info("  Conversations: ".count($conversations).' (6 open, 3 closed, 1 archived)');
        $this->command->info("  Messages: {$messageCount}");
    }
}
