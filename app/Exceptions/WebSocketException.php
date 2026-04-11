<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception for WebSocket (Reverb) related errors.
 */
class WebSocketException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly string $channel = '',
        public readonly ?int $conversationId = null,
        public readonly bool $isRecoverable = true,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
