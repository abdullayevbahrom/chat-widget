<?php

namespace Tests\Traits;

use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Http;

/**
 * Helper methods for mocking Telegram API interactions in tests.
 */
trait InteractsWithTelegram
{
    /**
     * Mock the TelegramBotService to return fake responses.
     */
    protected function mockTelegramApi(array $responses = []): void
    {
        $defaultResponses = [
            'getMe' => [
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'test_bot',
                ],
            ],
            'sendMessage' => [
                'ok' => true,
                'result' => [
                    'message_id' => 1,
                    'chat' => ['id' => 987654321, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'Test message',
                ],
            ],
            'sendPhoto' => [
                'ok' => true,
                'result' => [
                    'message_id' => 2,
                    'chat' => ['id' => 987654321, 'type' => 'private'],
                    'date' => 1700000000,
                    'photo' => [['file_id' => 'photo123']],
                ],
            ],
            'sendDocument' => [
                'ok' => true,
                'result' => [
                    'message_id' => 3,
                    'chat' => ['id' => 987654321, 'type' => 'private'],
                    'date' => 1700000000,
                    'document' => ['file_id' => 'doc123'],
                ],
            ],
            'setWebhook' => [
                'ok' => true,
                'result' => true,
            ],
            'deleteWebhook' => [
                'ok' => true,
                'result' => true,
            ],
            'getWebhookInfo' => [
                'ok' => true,
                'result' => [
                    'url' => 'https://example.com/api/telegram/webhook/test',
                    'has_custom_certificate' => false,
                    'pending_update_count' => 0,
                    'max_connections' => 40,
                    'allowed_updates' => ['message', 'callback_query'],
                ],
            ],
            'answerCallbackQuery' => [
                'ok' => true,
                'result' => true,
            ],
            'editMessageText' => [
                'ok' => true,
                'result' => [
                    'message_id' => 4,
                    'chat' => ['id' => 987654321, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'Edited message',
                ],
            ],
            'editMessageReplyMarkup' => [
                'ok' => true,
                'result' => [
                    'message_id' => 4,
                    'chat' => ['id' => 987654321, 'type' => 'private'],
                    'date' => 1700000000,
                    'text' => 'Message with edited markup',
                    'reply_markup' => ['inline_keyboard' => []],
                ],
            ],
            'getFile' => [
                'ok' => true,
                'result' => [
                    'file_id' => 'file123',
                    'file_unique_id' => 'unique123',
                    'file_size' => 12345,
                    'file_path' => 'documents/file_123.pdf',
                ],
            ],
        ];

        $mergedResponses = array_merge($defaultResponses, $responses);

        Http::fake(function ($request) use ($mergedResponses) {
            $url = $request->url();

            foreach ($mergedResponses as $endpoint => $response) {
                if (str_contains($url, $endpoint)) {
                    return Http::response($response, 200);
                }
            }

            // Default successful response
            return Http::response(['ok' => true, 'result' => null], 200);
        });
    }

    /**
     * Mock a Telegram webhook request with the given update data.
     */
    protected function mockTelegramWebhook(string $tenantSlug, array $updateData, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Telegram-Bot-Api-Secret-Token' => $secret ?? 'test-webhook-secret',
        ];

        return $this->postJson("/api/telegram/webhook/{$tenantSlug}", $updateData, $headers);
    }

    /**
     * Create a fake Telegram message update payload.
     */
    protected function fakeTelegramMessage(array $overrides = []): array
    {
        return [
            'update_id' => 123456789,
            'message' => array_merge([
                'message_id' => 1,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => 987654321,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Hello from Telegram!',
            ], $overrides),
        ];
    }

    /**
     * Create a fake Telegram callback query update payload.
     */
    protected function fakeTelegramCallbackQuery(array $overrides = []): array
    {
        return [
            'update_id' => 123456790,
            'callback_query' => array_merge([
                'id' => 'callback123',
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => 987654321, 'type' => 'private'],
                    'date' => time(),
                    'text' => 'Callback message',
                ],
                'data' => 'action:close_conversation',
            ], $overrides),
        ];
    }

    /**
     * Assert that a Telegram API endpoint was called.
     */
    protected function assertTelegramApiCalled(string $endpoint, int $times = 1): void
    {
        Http::assertSent(function ($request) use ($endpoint) {
            return str_contains($request->url(), $endpoint);
        }, $times);
    }

    /**
     * Create a fake Telegram photo message update.
     */
    protected function fakeTelegramPhotoMessage(array $overrides = []): array
    {
        return [
            'update_id' => 123456791,
            'message' => array_merge([
                'message_id' => 2,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 987654321,
                    'first_name' => 'Test',
                    'type' => 'private',
                ],
                'date' => time(),
                'caption' => 'Photo caption',
                'photo' => [
                    ['file_id' => 'photo_small', 'file_size' => 1000, 'width' => 100, 'height' => 100],
                    ['file_id' => 'photo_large', 'file_size' => 5000, 'width' => 800, 'height' => 600],
                ],
            ], $overrides),
        ];
    }

    /**
     * Create a fake Telegram document message update.
     */
    protected function fakeTelegramDocumentMessage(array $overrides = []): array
    {
        return [
            'update_id' => 123456792,
            'message' => array_merge([
                'message_id' => 3,
                'from' => [
                    'id' => 987654321,
                    'is_bot' => false,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 987654321,
                    'first_name' => 'Test',
                    'type' => 'private',
                ],
                'date' => time(),
                'caption' => 'Document caption',
                'document' => [
                    'file_id' => 'doc123',
                    'file_name' => 'test.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 12345,
                ],
            ], $overrides),
        ];
    }
}
