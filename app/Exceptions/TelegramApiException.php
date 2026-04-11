<?php

namespace App\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Typed exception for Telegram API errors.
 *
 * Provides structured error information including error codes,
 * retryability, and rate limit details.
 */
class TelegramApiException extends RuntimeException
{
    /**
     * Create a new TelegramApiException instance.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorDescription = null,
        public readonly ?string $tokenPrefix = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly bool $isRetryable = false,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception from a Telegram API response.
     */
    public static function fromApiResponse(array $response, string $tokenPrefix, int $httpStatus): self
    {
        $errorCode = (string) ($response['error_code'] ?? $httpStatus);
        $errorDescription = $response['description'] ?? 'Unknown Telegram API error';
        $retryAfter = $response['parameters']['retry_after'] ?? null;

        $isRetryable = self::determineRetryable($errorCode, $httpStatus);
        $retryAfterSeconds = $retryAfter !== null ? (int) $retryAfter : null;

        $message = "Telegram API error [{$errorCode}]: {$errorDescription}";

        return new self(
            message: $message,
            code: $httpStatus,
            errorCode: $errorCode,
            errorDescription: $errorDescription,
            tokenPrefix: $tokenPrefix,
            retryAfterSeconds: $retryAfterSeconds,
            isRetryable: $isRetryable,
        );
    }

    /**
     * Create a rate limited exception (429).
     */
    public static function rateLimited(int $retryAfter, string $tokenPrefix): self
    {
        return new self(
            message: "Telegram API rate limited. Retry after {$retryAfter} seconds.",
            code: 429,
            errorCode: '429',
            errorDescription: 'Too Many Requests',
            tokenPrefix: $tokenPrefix,
            retryAfterSeconds: $retryAfter,
            isRetryable: true,
        );
    }

    /**
     * Create an unauthorized exception (401).
     */
    public static function unauthorized(string $tokenPrefix): self
    {
        return new self(
            message: 'Telegram API unauthorized. Invalid bot token.',
            code: 401,
            errorCode: '401',
            errorDescription: 'Unauthorized',
            tokenPrefix: $tokenPrefix,
            isRetryable: false,
        );
    }

    /**
     * Create a not found exception (404).
     */
    public static function notFound(string $tokenPrefix): self
    {
        return new self(
            message: 'Telegram API resource not found. Chat may not exist.',
            code: 404,
            errorCode: '404',
            errorDescription: 'Not Found',
            tokenPrefix: $tokenPrefix,
            isRetryable: false,
        );
    }

    /**
     * Create a connection failed exception.
     */
    public static function connectionFailed(\Throwable $previous, ?string $tokenPrefix = null): self
    {
        return new self(
            message: 'Telegram API connection failed. Network error.',
            code: 0,
            errorCode: 'connection_error',
            errorDescription: 'Connection failed',
            tokenPrefix: $tokenPrefix,
            isRetryable: true,
            previous: $previous,
        );
    }

    /**
     * Determine if an error is retryable.
     */
    protected static function determineRetryable(?string $errorCode, int $httpStatus): bool
    {
        // Rate limit
        if ($errorCode === '429' || $httpStatus === 429) {
            return true;
        }

        // Server errors (5xx)
        if ($httpStatus >= 500) {
            return true;
        }

        // Timeout
        if ($errorCode === '408' || $httpStatus === 408) {
            return true;
        }

        // Client errors are not retryable
        if (in_array($errorCode, ['400', '401', '403', '404'], true)) {
            return false;
        }

        // Default: server-side errors are retryable
        return $httpStatus >= 500;
    }
}
