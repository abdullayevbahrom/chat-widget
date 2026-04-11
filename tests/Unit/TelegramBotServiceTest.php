<?php

namespace Tests\Unit;

use App\Services\TelegramBotService;
use PHPUnit\Framework\TestCase;

class TelegramBotServiceTest extends TestCase
{
    private TelegramBotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Don't initialize the full Laravel application for unit tests
        $this->service = new class extends TelegramBotService {
            public function __construct()
            {
                // Override to avoid config dependency
                $this->apiBaseUrl = 'https://api.telegram.org/bot';
            }
        };
    }

    public function test_validate_token_accepts_valid_format(): void
    {
        $validTokens = [
            '123456789:ABCdef-GHIjkl_MNOpqrSTUvwxYZ',
            '123456:ABC-DEF',
            '9876543210:AAAAABBBBBCCCCCDDDDDEEEEE-FFFFF',
            '1:abc',
        ];

        foreach ($validTokens as $token) {
            $this->assertTrue(
                $this->service->validateToken($token),
                "Token '{$token}' should be valid"
            );
        }
    }

    public function test_validate_token_rejects_invalid_format(): void
    {
        $invalidTokens = [
            'invalid-token',
            '123456789',
            'ABCdef-GHIjkl',
            ':ABCdef',
            '123456:',
            '123456:ABC def', // spaces not allowed
            '',
            'prefix-123456:ABCdef',
        ];

        foreach ($invalidTokens as $token) {
            $this->assertFalse(
                $this->service->validateToken($token),
                "Token '{$token}' should be invalid"
            );
        }
    }
}
