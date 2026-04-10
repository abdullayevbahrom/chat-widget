<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;

class VisitorTrackingService
{
    /** Maximum length for TEXT fields to prevent storage DoS (Issue #15) */
    protected const MAX_USER_AGENT_LENGTH = 500;
    protected const MAX_URL_LENGTH = 1024;
    protected const MAX_REFERER_LENGTH = 1024;

    /**
     * Track a visitor from the current HTTP request.
     *
     * Creates a new visitor record or updates an existing one
     * based on the session ID. All data is sanitized before storage.
     *
     * @return Visitor|null Returns null if tracking should be skipped
     */
    public function track(Request $request): ?Visitor
    {
        // Skip if session is not available
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return null;
        }

        $sessionId = $request->session()->getId();

        // Skip tracking bots if configured
        if (! $this->shouldTrackBots()) {
            $agent = $this->createAgent($request);
            if ($agent->isRobot()) {
                return null;
            }
        }

        // Build sanitized visitor data
        $data = $this->buildVisitorData($request);

        // Use upsert for atomic create-or-update (Issue #1: prevents race condition)
        // The unique constraint on session_id ensures only one record per session
        $now = now();
        $updates = array_merge($data, [
            'last_visit_at' => $now,
            'updated_at' => $now,
            // Increment visit_count atomically using raw expression (Issue #3)
            'visit_count' => DB::raw('COALESCE(visit_count, 0) + 1'),
        ]);

        // If the visitor becomes authenticated, link the user
        if (Auth::check()) {
            $updates['user_id'] = Auth::id();
            $updates['is_authenticated'] = true;
        }

        Visitor::updateOrCreate(
            ['session_id' => $sessionId],
            $updates
        );

        // Return the visitor record
        return Visitor::where('session_id', $sessionId)->first();
    }

    /**
     * Build sanitized visitor data from the request.
     * Handles all data extraction, sanitization, and encryption.
     *
     * @return array<string, mixed>
     */
    public function buildVisitorData(Request $request): array
    {
        $agent = $this->createAgent($request);

        return [
            'session_id' => $request->session()->getId(),
            'ip_address_encrypted' => $this->encryptIpAddress($this->resolveIpAddress($request)),
            'user_agent' => $this->sanitizeTextField($request->userAgent(), self::MAX_USER_AGENT_LENGTH),
            'referer' => $this->sanitizeTextField($request->headers->get('referer'), self::MAX_REFERER_LENGTH),
            'current_url' => $this->sanitizeTextField($request->fullUrl(), self::MAX_URL_LENGTH),
            'current_page' => $this->truncate($request->path(), 500),
            'device_type' => $this->getDeviceType($agent),
            'browser' => $this->truncate($agent->browser(), 100),
            'browser_version' => $this->truncate($agent->version($agent->browser()), 50),
            'platform' => $this->truncate($agent->platform(), 100),
            'platform_version' => $this->truncate($agent->version($agent->platform()), 50),
            'language' => $this->resolveLanguage($request),
            'is_authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'tenant_id' => $this->getCurrentTenantId(),
            'first_visit_at' => now(),
            'last_visit_at' => now(),
        ];
    }

    /**
     * Get a visitor record by session ID.
     */
    public function getVisitorBySession(string $sessionId): ?Visitor
    {
        return Visitor::where('session_id', $sessionId)->first();
    }

    /**
     * Clean up old visitor records.
     *
     * Uses the cleanup_after_days config value by default (Issue #8).
     *
     * @param  int|null  $days  Number of days to keep (null = use config)
     * @return int Number of deleted records
     */
    public function cleanupOldVisitors(?int $days = null): int
    {
        $days = $days ?? config('visitor-tracking.cleanup_after_days', 90);
        $cutoffDate = now()->subDays($days);

        return Visitor::where('last_visit_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get the decrypted IP address for a visitor.
     * Useful for admin panel display or analytics.
     */
    public function getDecryptedIpAddress(Visitor $visitor): ?string
    {
        if ($visitor->ip_address_encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($visitor->ip_address_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if a visitor should be tracked (not an ignored asset or bot).
     */
    public function shouldTrackRequest(Request $request): bool
    {
        // Skip ignored file extensions
        if ($this->shouldIgnoreExtension($request)) {
            return false;
        }

        // Skip bots if configured
        if (! $this->shouldTrackBots()) {
            $agent = $this->createAgent($request);
            if ($agent->isRobot()) {
                return false;
            }
        }

        return true;
    }

    // ──────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────

    /**
     * Resolve the client IP address, respecting proxy headers.
     * When behind a proxy/load balancer, Laravel's $request->ip()
     * automatically handles X-Forwarded-For if TrustProxies is configured (Issue #5).
     */
    protected function resolveIpAddress(Request $request): string
    {
        $ip = $request->ip();

        // Fallback if ip() returns null
        if ($ip === null || $ip === '') {
            $ip = $request->server('REMOTE_ADDR', '0.0.0.0');
        }

        return $ip;
    }

    /**
     * Encrypt the IP address for GDPR compliance (Issue #14).
     * The encryption key is Laravel's APP_KEY, so data is
     * tied to the application instance.
     */
    protected function encryptIpAddress(string $ip): string
    {
        return Crypt::encryptString($ip);
    }

    /**
     * Sanitize a text field to prevent stored XSS (Issue #12).
     * Strips HTML tags and applies htmlspecialchars for extra safety.
     */
    protected function sanitizeTextField(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        // Strip HTML tags first
        $cleaned = strip_tags($value);
        // Apply htmlspecialchars for any remaining special characters
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $this->truncate($cleaned, $maxLength);
    }

    /**
     * Truncate a string to a maximum length.
     */
    protected function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            return mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Resolve the preferred language from the request.
     * Handles the case where getPreferredLanguage() may return an array (Issue #10).
     */
    protected function resolveLanguage(Request $request): ?string
    {
        $language = $request->getPreferredLanguage();

        // In some PHP/configurations, getPreferredLanguage() can return an array
        if (is_array($language)) {
            $language = reset($language) ?: null;
        }

        return $language !== null ? $this->truncate($language, 20) : null;
    }

    /**
     * Create an Agent instance from the request.
     */
    protected function createAgent(Request $request): Agent
    {
        $agent = new Agent;
        $agent->setUserAgent($request->userAgent() ?? '');

        return $agent;
    }

    /**
     * Determine the device type from the agent.
     */
    protected function getDeviceType(Agent $agent): string
    {
        if ($agent->isRobot()) {
            return 'bot';
        }

        if ($agent->isTablet()) {
            return 'tablet';
        }

        if ($agent->isMobile()) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Get the current tenant ID from the tenant context.
     * Tenant::current() is confirmed to exist in the Tenant model (Issue #9).
     */
    protected function getCurrentTenantId(): ?int
    {
        $tenant = Tenant::current();

        return $tenant?->id;
    }

    /**
     * Check if bot tracking is enabled.
     */
    protected function shouldTrackBots(): bool
    {
        return config('visitor-tracking.track_bots', false);
    }

    /**
     * Check if the request should be ignored based on file extension.
     */
    protected function shouldIgnoreExtension(Request $request): bool
    {
        $ignoreExtensions = config('visitor-tracking.ignore_extensions', [
            'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg',
            'woff', 'woff2', 'ttf', 'eot', 'map',
        ]);

        $path = $request->path();
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array(strtolower($extension), $ignoreExtensions, true);
    }
}
