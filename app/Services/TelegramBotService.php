<?php

namespace App\Services;

use App\Exceptions\TelegramApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBotService
{
    /**
     * Default Telegram Bot API base URL.
     */
    protected string $apiBaseUrl;

    /**
     * Maximum retry attempts for API requests.
     */
    protected int $maxRetries = 3;

    /**
     * Circuit breaker threshold (consecutive failures before opening circuit).
     */
    protected int $circuitBreakerThreshold = 5;

    /**
     * Circuit breaker cooldown period in seconds.
     */
    protected int $circuitBreakerCooldown = 60;

    public function __construct()
    {
        $this->apiBaseUrl = config('telegram.bot_api_base_url', 'https://api.telegram.org/bot');
    }

    /**
     * Get bot information using the getMe API method.
     */
    public function getBotInfo(string $token): array
    {
        $response = $this->makeRequest('get', 'getMe', $token);

        return $response['result'] ?? [];
    }

    /**
     * Set a webhook for the bot.
     */
    public function setWebhook(string $token, string $webhookUrl, ?string $secret = null): array
    {
        $payload = ['url' => $webhookUrl];

        if ($secret !== null) {
            $payload['secret_token'] = $secret;
        }

        return $this->makeRequest('post', 'setWebhook', $token, $payload);
    }

    /**
     * Delete the bot's webhook.
     */
    public function deleteWebhook(string $token): array
    {
        return $this->makeRequest('post', 'deleteWebhook', $token);
    }

    /**
     * Get webhook information.
     */
    public function getWebhookInfo(string $token): array
    {
        return $this->makeRequest('get', 'getWebhookInfo', $token);
    }

    /**
     * Validate a Telegram bot token format.
     */
    public function validateToken(string $token): bool
    {
        return (bool) preg_match('/^\d+:[\w-]+$/', $token);
    }

    /**
     * Maximum allowed message length for Telegram.
     */
    public const MAX_MESSAGE_LENGTH = 4096;

    /**
     * Send a message to a Telegram chat.
     *
     * @throws TelegramApiException
     * @throws \InvalidArgumentException
     */
    public function sendMessage(
        string $token,
        string|int $chatId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
    ): array {
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                'Telegram message text cannot exceed '.self::MAX_MESSAGE_LENGTH.' characters.'
            );
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return $this->makeRequest('post', 'sendMessage', $token, $payload, [
            'method' => 'sendMessage',
            'chat_id' => $chatId,
        ]);
    }

    /**
     * Answer a callback query (acknowledge inline button press).
     *
     * @throws TelegramApiException
     */
    public function answerCallbackQuery(
        string $token,
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false,
    ): array {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = mb_substr($text, 0, 200);
        }

        if ($showAlert) {
            $payload['show_alert'] = true;
        }

        return $this->makeRequest('post', 'answerCallbackQuery', $token, $payload, [
            'method' => 'answerCallbackQuery',
            'callback_query_id' => $callbackQueryId,
        ]);
    }

    /**
     * Edit text of an existing message.
     *
     * @throws TelegramApiException
     * @throws \InvalidArgumentException
     */
    public function editMessageText(
        string $token,
        string|int $chatId,
        int $messageId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
    ): array {
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                'Telegram edit message text cannot exceed '.self::MAX_MESSAGE_LENGTH.' characters.'
            );
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return $this->makeRequest('post', 'editMessageText', $token, $payload, [
            'method' => 'editMessageText',
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Edit only the reply markup (inline keyboard) of an existing message.
     *
     * @throws TelegramApiException
     */
    public function editMessageReplyMarkup(
        string $token,
        string|int $chatId,
        int $messageId,
        array $replyMarkup,
    ): array {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];

        return $this->makeRequest('post', 'editMessageReplyMarkup', $token, $payload, [
            'method' => 'editMessageReplyMarkup',
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Get file path from Telegram API.
     */
    public function getFilePath(string $token, string $fileId): ?string
    {
        try {
            $response = $this->makeRequest('get', 'getFile', $token, ['file_id' => $fileId], [
                'method' => 'getFile',
                'file_id' => $fileId,
            ]);

            $filePath = $response['result']['file_path'] ?? null;

            return is_string($filePath) && $filePath !== '' ? $filePath : null;
        } catch (TelegramApiException $e) {
            Log::warning('Telegram Bot API getFile failed', [
                'channel' => 'telegram',
                'method' => 'getFile',
                'token_prefix' => $this->sanitizeToken($token),
                'file_id' => $fileId,
                'error' => $e->getMessage(),
                'error_code' => $e->errorCode,
                'is_retryable' => $e->isRetryable,
            ]);

            return null;
        }
    }

    /**
     * Download a file from Telegram.
     */
    public function downloadFile(string $token, string $filePath): ?string
    {
        $downloadBaseUrl = str_replace('/bot', '/file/bot', $this->apiBaseUrl);
        $tokenPrefix = $this->sanitizeToken($token);

        try {
            $response = Http::timeout(20)->get("{$downloadBaseUrl}{$token}/{$filePath}");

            if (! $response->successful()) {
                Log::warning('Telegram file download failed', [
                    'channel' => 'telegram',
                    'method' => 'downloadFile',
                    'token_prefix' => $tokenPrefix,
                    'file_path' => $filePath,
                    'http_status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (ConnectionException $e) {
            Log::warning('Telegram file download connection failed', [
                'channel' => 'telegram',
                'method' => 'downloadFile',
                'token_prefix' => $tokenPrefix,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check the webhook status and return detailed information.
     */
    public function checkWebhookStatus(string $token): array
    {
        $info = $this->getWebhookInfo($token);

        $hasUrl = isset($info['url']) && $info['url'] !== '';
        $hasError = isset($info['last_error_date']) && isset($info['last_error_message']);

        return [
            'url' => $info['url'] ?? null,
            'has_custom_certificate' => $info['has_custom_certificate'] ?? false,
            'pending_update_count' => $info['pending_update_count'] ?? 0,
            'last_error_date' => $info['last_error_date'] ?? null,
            'last_error_message' => $info['last_error_message'] ?? null,
            'max_connections' => $info['max_connections'] ?? null,
            'allowed_updates' => $info['allowed_updates'] ?? [],
            'ip_address' => $info['ip_address'] ?? null,
            'is_active' => $hasUrl && ! $hasError,
            'status' => $hasUrl
                ? ($hasError ? 'error' : 'active')
                : 'not_set',
        ];
    }

    /**
     * Make an HTTP request to the Telegram API with retry logic.
     *
     * @throws TelegramApiException
     */
    protected function makeRequest(
        string $method,
        string $endpoint,
        string $token,
        array $payload = [],
        array $logContext = [],
    ): array {
        $tokenPrefix = $this->sanitizeToken($token);

        // Check circuit breaker
        if ($this->isCircuitOpen($token)) {
            throw TelegramApiException::connectionFailed(
                new \Exception('Circuit breaker is open — too many consecutive failures'),
                $tokenPrefix
            );
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->executeRequest($method, $endpoint, $token, $payload, $logContext);

                // Reset circuit breaker on success
                $this->resetCircuitBreaker($token);

                return $response;
            } catch (TelegramApiException $e) {
                $lastException = $e;

                // Don't retry non-retryable errors
                if (! $e->isRetryable) {
                    $this->recordCircuitFailure($token);
                    throw $e;
                }

                // Handle rate limiting — use Retry-After if available
                if ($e->retryAfterSeconds !== null) {
                    Log::warning('Telegram API rate limited, waiting for Retry-After', [
                        'channel' => 'telegram',
                        ...$logContext,
                        'token_prefix' => $tokenPrefix,
                        'retry_after_seconds' => $e->retryAfterSeconds,
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxRetries,
                    ]);

                    if ($attempt < $this->maxRetries) {
                        sleep($e->retryAfterSeconds);
                        continue;
                    }
                }

                // Log retryable error
                Log::warning('Telegram API request failed, will retry', [
                    'channel' => 'telegram',
                    ...$logContext,
                    'token_prefix' => $tokenPrefix,
                    'error_code' => $e->errorCode,
                    'error_description' => $e->errorDescription,
                    'is_retryable' => $e->isRetryable,
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxRetries,
                ]);

                // Wait before retry (exponential backoff + jitter)
                if ($attempt < $this->maxRetries) {
                    $backoff = $this->calculateBackoff($attempt);
                    usleep($backoff * 1000);
                }
            } catch (ConnectionException $e) {
                $lastException = TelegramApiException::connectionFailed($e, $tokenPrefix);

                Log::warning('Telegram API connection error', [
                    'channel' => 'telegram',
                    ...$logContext,
                    'token_prefix' => $tokenPrefix,
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxRetries) {
                    $backoff = $this->calculateBackoff($attempt);
                    usleep($backoff * 1000);
                }
            }
        }

        // All retries exhausted
        $this->recordCircuitFailure($token);

        Log::error('Telegram API request failed after all retries', [
            'channel' => 'telegram',
            ...$logContext,
            'token_prefix' => $tokenPrefix,
            'attempts' => $this->maxRetries,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new TelegramApiException(
            'Telegram API request failed after retries',
            0,
            null,
            'unknown',
            'All retries exhausted',
            $tokenPrefix,
            isRetryable: false,
        );
    }

    /**
     * Execute a single HTTP request to the Telegram API.
     *
     * @throws TelegramApiException
     */
    protected function executeRequest(
        string $method,
        string $endpoint,
        string $token,
        array $payload,
        array $logContext,
    ): array {
        $tokenPrefix = $this->sanitizeToken($token);

        $http = Http::timeout(30);

        $response = $method === 'get'
            ? $http->get("{$this->apiBaseUrl}{$token}/{$endpoint}", $payload)
            : $http->post("{$this->apiBaseUrl}{$token}/{$endpoint}", $payload);

        // Handle non-successful HTTP status
        if (! $response->successful()) {
            $httpStatus = $response->status();
            $errorDescription = $response->json('description', 'Unknown error');
            $errorCode = (string) ($response->json('error_code') ?? $httpStatus);

            // Check for rate limiting
            if ($httpStatus === 429) {
                $retryAfter = $response->header('Retry-After');

                if ($retryAfter === null) {
                    $retryAfter = $response->json('parameters.retry_after');
                }

                $retryAfterSeconds = $retryAfter !== null ? (int) $retryAfter : null;

                Log::error('Telegram API rate limited', [
                    'channel' => 'telegram',
                    ...$logContext,
                    'token_prefix' => $tokenPrefix,
                    'error_code' => $errorCode,
                    'error_type' => 'rate_limited',
                    'retry_after_seconds' => $retryAfterSeconds,
                    'http_status' => $httpStatus,
                ]);

                throw TelegramApiException::rateLimited($retryAfterSeconds ?? 30, $tokenPrefix);
            }

            // Handle specific error codes
            if ($httpStatus === 401) {
                Log::error('Telegram API unauthorized', [
                    'channel' => 'telegram',
                    ...$logContext,
                    'token_prefix' => $tokenPrefix,
                    'error_code' => $errorCode,
                    'error_type' => 'unauthorized',
                    'http_status' => $httpStatus,
                ]);

                throw TelegramApiException::unauthorized($tokenPrefix);
            }

            if ($httpStatus === 404) {
                Log::error('Telegram API not found', [
                    'channel' => 'telegram',
                    ...$logContext,
                    'token_prefix' => $tokenPrefix,
                    'error_code' => $errorCode,
                    'error_type' => 'not_found',
                    'http_status' => $httpStatus,
                ]);

                throw TelegramApiException::notFound($tokenPrefix);
            }

            // Generic API error
            $exception = TelegramApiException::fromApiResponse(
                $response->json(),
                $tokenPrefix,
                $httpStatus
            );

            Log::error('Telegram API request failed', [
                'channel' => 'telegram',
                ...$logContext,
                'token_prefix' => $tokenPrefix,
                'error_code' => $exception->errorCode,
                'error_description' => $exception->errorDescription,
                'error_type' => $this->classifyError($httpStatus),
                'is_retryable' => $exception->isRetryable,
                'http_status' => $httpStatus,
            ]);

            throw $exception;
        }

        // Handle API returning ok: false
        if (! $response->json('ok')) {
            $errorDescription = $response->json('description', 'Unknown error');

            Log::error('Telegram API returned error response', [
                'channel' => 'telegram',
                ...$logContext,
                'token_prefix' => $tokenPrefix,
                'error_description' => $errorDescription,
            ]);

            throw new TelegramApiException(
                "Telegram API error: {$errorDescription}",
                0,
                null,
                'api_error',
                $errorDescription,
                $tokenPrefix,
                isRetryable: false,
            );
        }

        return $response->json();
    }

    /**
     * Calculate exponential backoff with jitter.
     *
     * Formula: min(1000 * 2^attempt + random(0-500ms), 10000ms)
     */
    protected function calculateBackoff(int $attempt): int
    {
        $baseDelay = 1000 * pow(2, $attempt - 1);
        $jitter = random_int(0, 500);

        return min($baseDelay + $jitter, 10000);
    }

    /**
     * Check if the circuit breaker is open for a given token.
     */
    protected function isCircuitOpen(string $token): bool
    {
        $cacheKey = $this->circuitBreakerKey($token);
        $state = Cache::get($cacheKey);

        if ($state === null) {
            return false;
        }

        // Check if cooldown period has passed
        if (time() - $state['timestamp'] >= $this->circuitBreakerCooldown) {
            // Half-open state — allow one request through
            Cache::put($cacheKey, [
                'failures' => 0,
                'timestamp' => time(),
                'state' => 'half-open',
            ], $this->circuitBreakerCooldown * 2);

            return false;
        }

        return $state['state'] === 'open';
    }

    /**
     * Record a circuit breaker failure.
     */
    protected function recordCircuitFailure(string $token): void
    {
        $cacheKey = $this->circuitBreakerKey($token);
        $state = Cache::get($cacheKey, ['failures' => 0, 'timestamp' => time(), 'state' => 'closed']);

        $state['failures']++;
        $state['timestamp'] = time();

        if ($state['failures'] >= $this->circuitBreakerThreshold) {
            $state['state'] = 'open';
        }

        Cache::put($cacheKey, $state, $this->circuitBreakerCooldown * 2);
    }

    /**
     * Reset the circuit breaker on success.
     */
    protected function resetCircuitBreaker(string $token): void
    {
        $cacheKey = $this->circuitBreakerKey($token);
        Cache::forget($cacheKey);
    }

    /**
     * Get the cache key for circuit breaker state.
     */
    protected function circuitBreakerKey(string $token): string
    {
        return 'telegram_circuit_' . md5($token);
    }

    /**
     * Classify an error type for logging.
     */
    protected function classifyError(int $httpStatus): string
    {
        return match (true) {
            $httpStatus === 429 => 'rate_limited',
            $httpStatus === 401 => 'unauthorized',
            $httpStatus === 403 => 'forbidden',
            $httpStatus === 404 => 'not_found',
            $httpStatus >= 500 => 'server_error',
            default => 'unknown',
        };
    }

    /**
     * Sanitize a bot token for safe logging.
     */
    protected function sanitizeToken(string $token): string
    {
        return preg_replace('/^(\d+:).{4,}([A-Za-z0-9_-]{4})$/', '$1****$2', $token) ?? substr($token, 0, 10) . '****';
    }
}
