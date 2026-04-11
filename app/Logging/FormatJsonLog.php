<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;

/**
 * Configures Monolog to use JSON formatting for structured logs.
 */
class FormatJsonLog
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(
                new JsonFormatter(JsonFormatter::BATCH_MODE_JSON)
            );
        }
    }
}
