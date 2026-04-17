<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\Project;
use App\Services\TelegramMessageFormatter;
use PHPUnit\Framework\TestCase;

class TelegramMessageFormatterTest extends TestCase
{
    public function test_escape_escapes_special_markdown_characters(): void
    {
        $input = 'Hello_World *test* [link](url) ~code~ `inline` >quote #tag +add -sub =eq |pipe {brace} .dot !bang';
        $escaped = TelegramMessageFormatter::escape($input);

        $this->assertStringContainsString('\_', $escaped);
        $this->assertStringContainsString('\*', $escaped);
        $this->assertStringContainsString('\[', $escaped);
        $this->assertStringContainsString('\(', $escaped);
        $this->assertStringContainsString('\~', $escaped);
        $this->assertStringContainsString('\`', $escaped);
        $this->assertStringContainsString('\>', $escaped);
        $this->assertStringContainsString('\#', $escaped);
        $this->assertStringContainsString('\+', $escaped);
        $this->assertStringContainsString('\-', $escaped);
        $this->assertStringContainsString('\=', $escaped);
        $this->assertStringContainsString('\|', $escaped);
        $this->assertStringContainsString('\{', $escaped);
        $this->assertStringContainsString('\.', $escaped);
        $this->assertStringContainsString('\!', $escaped);
    }

    public function test_escape_does_not_double_escape(): void
    {
        $input = 'already\_escaped';
        $escaped = TelegramMessageFormatter::escape($input);

        // The backslash itself will be escaped
        $this->assertStringContainsString('\\\\', $escaped);
    }

    public function test_format_returns_parse_mode_markdownv2(): void
    {
        $message = new class extends Message
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['body'] = 'Test message body';
                $this->attributes['message_type'] = Message::TYPE_TEXT;
                $this->attributes['attachments'] = null;
                $this->attributes['conversation_id'] = 42;
                $this->attributes['metadata'] = '[]';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'My Project';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project);

        $this->assertArrayHasKey('telegram_text', $result);
        $this->assertArrayHasKey('parse_mode', $result);
        $this->assertEquals('MarkdownV2', $result['parse_mode']);
        $this->assertStringContainsString('New message', $result['telegram_text']);
    }

    public function test_format_includes_visitor_name_and_email(): void
    {
        $message = new class extends Message
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['body'] = 'Hello from visitor';
                $this->attributes['message_type'] = Message::TYPE_TEXT;
                $this->attributes['attachments'] = null;
                $this->attributes['conversation_id'] = 1;
                $this->attributes['metadata'] = '[]';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Test';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project, [
            'visitor_name' => 'Alice',
            'visitor_email' => 'alice@test.com',
        ]);

        $this->assertStringContainsString('Alice', $result['telegram_text']);
        $this->assertStringContainsString('alice@test\.com', $result['telegram_text']);
    }

    public function test_format_handles_null_body(): void
    {
        $message = new class extends Message
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['body'] = null;
                $this->attributes['message_type'] = Message::TYPE_TEXT;
                $this->attributes['attachments'] = null;
                $this->attributes['conversation_id'] = 1;
                $this->attributes['metadata'] = '[]';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Test';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project);

        // Should not throw, and should contain header info
        $this->assertStringContainsString('New message', $result['telegram_text']);
    }

    public function test_format_truncates_long_body(): void
    {
        $longBody = str_repeat('A', 2000);

        $message = new class($longBody) extends Message
        {
            public function __construct(public string $longBody = '')
            {
                parent::__construct();
                $this->attributes['body'] = $this->longBody;
                $this->attributes['message_type'] = Message::TYPE_TEXT;
                $this->attributes['attachments'] = null;
                $this->attributes['conversation_id'] = 1;
                $this->attributes['metadata'] = '[]';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Test';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project);

        // Total text must be within Telegram's 4096 limit
        $this->assertLessThanOrEqual(
            TelegramMessageFormatter::MAX_MESSAGE_LENGTH,
            mb_strlen($result['telegram_text'])
        );
    }

    public function test_format_handles_attachments(): void
    {
        $attachments = json_encode([
            ['original_name' => 'document.pdf', 'name' => 'doc.pdf'],
            ['original_name' => 'photo.jpg', 'name' => 'photo.jpg'],
        ], JSON_THROW_ON_ERROR);

        $message = new class($attachments) extends Message
        {
            public function __construct(public string $attachmentsJson = '')
            {
                parent::__construct();
                $this->attributes['body'] = 'Check this file';
                $this->attributes['message_type'] = Message::TYPE_FILE;
                $this->attributes['attachments'] = $this->attachmentsJson;
                $this->attributes['conversation_id'] = 5;
                $this->attributes['metadata'] = '{"attachment_count":2}';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Project';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project);

        $this->assertStringContainsString('document\.pdf', $result['telegram_text']);
        $this->assertStringContainsString('photo\.jpg', $result['telegram_text']);
    }

    public function test_format_uses_default_values_for_missing_visitor_data(): void
    {
        $message = new class extends Message
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['body'] = 'Test';
                $this->attributes['message_type'] = Message::TYPE_TEXT;
                $this->attributes['attachments'] = null;
                $this->attributes['conversation_id'] = 1;
                $this->attributes['metadata'] = '[]';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Project';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project, []);

        $this->assertStringContainsString('Visitor', $result['telegram_text']);
    }

    public function test_format_image_message_type(): void
    {
        $message = new class extends Message
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['body'] = 'Look at this image';
                $this->attributes['message_type'] = Message::TYPE_IMAGE;
                $this->attributes['attachments'] = null;
                $this->attributes['conversation_id'] = 1;
                $this->attributes['metadata'] = '[]';
            }
        };

        $project = new class extends Project
        {
            public function __construct()
            {
                parent::__construct();
                $this->attributes['name'] = 'Project';
            }
        };

        $result = TelegramMessageFormatter::format($message, $project);

        $this->assertStringContainsString('image', $result['telegram_text']);
    }
}
