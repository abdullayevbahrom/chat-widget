<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception for broadcast failures.
 */
class BroadcastFailedException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly string $eventName = '',
        public readonly array $channels = [],
    ) {
        parent::__construct($message, $code, $previous);
    }
}
