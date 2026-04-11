<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Anti-replay token service for widget close endpoint.
 *
 * Generates and validates one-time-use tokens to prevent
 * replay attacks on the close conversation endpoint.
 */
class WidgetAntiReplayService
{
    /**
     * Token TTL in seconds (5 minutes).
     */
    protected int $ttlSeconds = 300;

    /**
     * Generate an anti-replay token for a visitor session.
     */
    public function generateToken(int $projectId, int $visitorId, string $sessionId): string
    {
        $token = Str::random(32);

        $cacheKey = $this->cacheKey($projectId, $visitorId, $token, $sessionId);

        Cache::put($cacheKey, [
            'project_id' => $projectId,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'created_at' => now()->getTimestamp(),
        ], $this->ttlSeconds);

        return $token;
    }

    /**
     * Validate and consume an anti-replay token.
     *
     * Returns true if the token is valid and has been consumed.
     * Returns false if the token is invalid, expired, or already used.
     */
    public function validateToken(int $projectId, int $visitorId, string $token, ?string $sessionId = null): bool
    {
        $cacheKey = $this->cacheKey($projectId, $visitorId, $token, $sessionId);

        $data = Cache::pull($cacheKey); // Atomically get and delete

        if ($data === null) {
            return false;
        }

        // Verify the token matches the expected visitor
        if (($data['project_id'] ?? null) !== $projectId) {
            return false;
        }

        if (($data['visitor_id'] ?? null) !== $visitorId) {
            return false;
        }

        // Verify session matches if provided
        if ($sessionId !== null && ($data['session_id'] ?? null) !== $sessionId) {
            return false;
        }

        return true;
    }

    /**
     * Build the cache key for an anti-replay token.
     */
    protected function cacheKey(int $projectId, int $visitorId, string $token, ?string $sessionId = null): string
    {
        $key = "anti-replay:{$projectId}:{$visitorId}";

        if ($sessionId !== null) {
            $key .= ":{$sessionId}";
        }

        $key .= ":{$token}";

        return $key;
    }
}
