<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Broadcast;

/**
 * Helper methods for mocking and asserting WebSocket/broadcast interactions.
 */
trait InteractsWithWebSockets
{
    /**
     * Set up broadcasting fakes for WebSocket tests.
     */
    protected function mockBroadcasting(): void
    {
        Broadcast::fake();
    }

    /**
     * Assert that an event was broadcast on the given channel.
     */
    protected function assertBroadcastOn(string $eventClass, string $channel): void
    {
        Broadcast::assertSentOn($eventClass, function ($channels) use ($channel) {
            foreach ($channels as $c) {
                $name = is_string($c) ? $c : $c->name;
                if ($name === $channel) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Assert that an event was broadcast with the given payload data.
     */
    protected function assertBroadcastPayload(string $eventClass, array $expectedPayload): void
    {
        Broadcast::assertSentOn($eventClass, function ($channels, $event) use ($expectedPayload) {
            $broadcastData = $event->broadcastWith();

            foreach ($expectedPayload as $key => $value) {
                if (! isset($broadcastData[$key])) {
                    return false;
                }

                if ($broadcastData[$key] !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Assert that a specific event was broadcast a given number of times.
     */
    protected function assertBroadcastTimes(string $eventClass, int $times = 1): void
    {
        Broadcast::assertSentTimes($eventClass, $times);
    }

    /**
     * Assert that a specific event was NOT broadcast.
     */
    protected function assertNotBroadcast(string $eventClass): void
    {
        Broadcast::assertNotSent($eventClass);
    }

    /**
     * Assert that an event was broadcast on multiple channels.
     *
     * @param  array<string>  $channels
     */
    protected function assertBroadcastOnMultipleChannels(string $eventClass, array $channels): void
    {
        Broadcast::assertSentOn($eventClass, function ($broadcastChannels) use ($channels) {
            $broadcastNames = array_map(function ($c) {
                return is_string($c) ? $c : $c->name;
            }, $broadcastChannels);

            foreach ($channels as $channel) {
                if (! in_array($channel, $broadcastNames, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Assert that an event was NOT broadcast on the given channel.
     */
    protected function assertNotBroadcastOn(string $eventClass, string $channel): void
    {
        Broadcast::assertNotSentOn($eventClass, $channel);
    }

    /**
     * Get the broadcast data for an event from the fake.
     */
    protected function getBroadcastData(string $eventClass): array
    {
        $sent = Broadcast::sent($eventClass);

        if (empty($sent)) {
            return [];
        }

        $event = $sent[0];

        return [
            'channels' => array_map(fn ($c) => is_string($c) ? $c : $c->name, $event->broadcastOn()),
            'name' => $event->broadcastAs(),
            'payload' => $event->broadcastWith(),
        ];
    }

    /**
     * Fake the Reverb/Redis broadcasting driver.
     *
     * This prevents actual WebSocket connections during testing.
     */
    protected function fakeReverb(): void
    {
        config(['broadcasting.default' => 'null']);
        Broadcast::fake();
    }
}
