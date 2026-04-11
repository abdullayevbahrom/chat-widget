<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Project;
use App\Services\TelegramInlineKeyboard;
use PHPUnit\Framework\TestCase;

class TelegramInlineKeyboardTest extends TestCase
{
    public function test_build_for_conversation_returns_valid_structure(): void
    {
        $conversation = new class extends Conversation {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['id'] = 42;
            }
        };

        $project = new class extends Project {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Test Project';
            }
        };

        $keyboard = TelegramInlineKeyboard::buildForConversation($conversation, $project);

        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertIsArray($keyboard['inline_keyboard']);

        // Should have 2 rows
        $this->assertCount(2, $keyboard['inline_keyboard']);

        // First row: Reply and Close
        $this->assertCount(2, $keyboard['inline_keyboard'][0]);
        $this->assertEquals('💬 Javob', $keyboard['inline_keyboard'][0][0]['text']);
        $this->assertEquals('reply:42', $keyboard['inline_keyboard'][0][0]['callback_data']);
        $this->assertEquals('🔒 Yopish', $keyboard['inline_keyboard'][0][1]['text']);
        $this->assertEquals('close:42', $keyboard['inline_keyboard'][0][1]['callback_data']);

        // Second row: Assign and Dashboard
        $this->assertCount(2, $keyboard['inline_keyboard'][1]);
        $this->assertEquals('👤 Menga tayinlash', $keyboard['inline_keyboard'][1][0]['text']);
        $this->assertEquals('assign:42', $keyboard['inline_keyboard'][1][0]['callback_data']);
        $this->assertArrayHasKey('url', $keyboard['inline_keyboard'][1][1]);
    }

    public function test_callback_data_is_within_64_byte_limit(): void
    {
        $conversation = new class extends Conversation {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['id'] = 999999;
            }
        };

        $project = new class extends Project {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $keyboard = TelegramInlineKeyboard::buildForConversation($conversation, $project);

        foreach ($keyboard['inline_keyboard'] as $row) {
            foreach ($row as $button) {
                if (isset($button['callback_data'])) {
                    $this->assertLessThanOrEqual(
                        64,
                        strlen($button['callback_data']),
                        "Callback data '{$button['callback_data']}' exceeds 64 byte limit"
                    );
                }
            }
        }
    }

    public function test_build_closed_keyboard(): void
    {
        $keyboard = TelegramInlineKeyboard::buildClosedKeyboard();

        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertCount(1, $keyboard['inline_keyboard']);
        $this->assertEquals('✅ Suhbat yopildi', $keyboard['inline_keyboard'][0][0]['text']);
    }

    public function test_build_after_assignment_keyboard(): void
    {
        $conversation = new class extends Conversation {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['id'] = 10;
            }
        };

        $project = new class extends Project {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $keyboard = TelegramInlineKeyboard::buildAfterAssignment($conversation, $project);

        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertCount(2, $keyboard['inline_keyboard']);

        // Should have "Assigned" acknowledgment button
        $foundAssigned = false;
        foreach ($keyboard['inline_keyboard'] as $row) {
            foreach ($row as $button) {
                if (isset($button['text']) && str_contains($button['text'], 'Tayinlangan')) {
                    $foundAssigned = true;
                }
            }
        }
        $this->assertTrue($foundAssigned, 'Keyboard should contain assigned acknowledgment button');
    }

    public function test_dashboard_url_contains_conversation_id(): void
    {
        $conversation = new class extends Conversation {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['id'] = 123;
            }
        };

        $project = new class extends Project {
            public function __construct()
            {
                parent::__construct();
            }
        };

        $_ENV['APP_URL'] = 'https://app.example.com';

        $keyboard = TelegramInlineKeyboard::buildForConversation($conversation, $project);

        $dashboardUrl = $keyboard['inline_keyboard'][1][1]['url'];
        $this->assertStringContainsString('123', $dashboardUrl);
        $this->assertStringContainsString('conversations', $dashboardUrl);
        $this->assertStringContainsString('app.example.com', $dashboardUrl);
    }
}
