<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\ClientConnected;
use Laravel\Reverb\Events\ClientDisconnected;

/**
 * Listener for WebSocket connection events.
 *
 * Logs client connections and disconnections with
 * structured context for operational visibility.
 */
class LogWebSocketConnection
{
    /**
     * Handle the ClientConnected event.
     */
    public function handleClientConnected(ClientConnected $event): void
    {
        Log::debug('WebSocket client connected', [
            'channel' => 'websocket',
            'connection_id' => $event->connection->id() ?? null,
            'app_id' => $event->connection->app()->id() ?? null,
        ]);
    }

    /**
     * Handle the ClientDisconnected event.
     */
    public function handleClientDisconnected(ClientDisconnected $event): void
    {
        Log::debug('WebSocket client disconnected', [
            'channel' => 'websocket',
            'connection_id' => $event->connection->id() ?? null,
            'app_id' => $event->connection->app()->id() ?? null,
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            ClientConnected::class => 'handleClientConnected',
            ClientDisconnected::class => 'handleClientDisconnected',
        ];
    }
}
