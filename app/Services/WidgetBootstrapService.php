<?php

namespace App\Services;

use App\Models\ProjectDomain;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class WidgetBootstrapService
{
    /**
     * Bootstrap token TTL in seconds.
     *
     * Reduced from 900s (15 min) to 300s (5 min) to minimize the window
     * for token replay attacks. The widget automatically refreshes the
     * token on subsequent API calls.
     */
    protected int $ttlSeconds = 300;

    public function issueToken(Project $project, string $trustedOrigin): string
    {
        $normalizedOrigin = $this->normalizeOrigin($trustedOrigin);

        if ($normalizedOrigin === null) {
            Log::warning('Refused to issue widget bootstrap token because the trusted origin is invalid.', [
                'project_id' => $project->id,
                'trusted_origin' => $trustedOrigin,
            ]);

            throw new \InvalidArgumentException('Trusted origin must be a valid http/https origin.');
        }

        $payload = [
            'project_id' => $project->id,
            'trusted_origin' => $normalizedOrigin,
            'issued_at' => now()->getTimestamp(),
            'expires_at' => now()->addSeconds($this->ttlSeconds)->getTimestamp(),
        ];

        Log::info('Issuing widget bootstrap token.', [
            'project_id' => $project->id,
            'trusted_origin' => $trustedOrigin,
            'expires_at' => $payload['expires_at'],
        ]);

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{project_id:int, trusted_origin:string, issued_at:int, expires_at:int}|null
     */
    public function decodeToken(?string $token): ?array
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        try {
            /** @var mixed $payload */
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::warning('Failed to decode widget bootstrap token.', [
                'exception' => $exception::class,
            ]);

            return null;
        }

        if (
            ! is_array($payload)
            || ! isset($payload['project_id'], $payload['trusted_origin'], $payload['issued_at'], $payload['expires_at'])
            || ! is_int($payload['project_id'])
            || ! is_string($payload['trusted_origin'])
            || ! is_int($payload['issued_at'])
            || ! is_int($payload['expires_at'])
        ) {
            Log::warning('Rejected widget bootstrap token because the payload shape is invalid.');

            return null;
        }

        $payload['trusted_origin'] = $this->normalizeOrigin($payload['trusted_origin']);

        if ($payload['trusted_origin'] === null) {
            Log::warning('Rejected widget bootstrap token because the trusted origin is invalid.', [
                'project_id' => $payload['project_id'],
            ]);

            return null;
        }

        if (now()->getTimestamp() >= $payload['expires_at']) {
            Log::warning('Rejected expired widget bootstrap token.', [
                'project_id' => $payload['project_id'],
                'trusted_origin' => $payload['trusted_origin'],
                'expires_at' => $payload['expires_at'],
            ]);

            return null;
        }

        return $payload;
    }

    public function normalizeOrigin(?string $candidate): ?string
    {
        return ProjectDomain::normalizeDomainInput($candidate);
    }

    public function requestMatchesTrustedOrigin(Request $request, string $trustedOrigin): bool
    {
        $normalizedTrustedOrigin = $this->normalizeOrigin($trustedOrigin);

        if ($normalizedTrustedOrigin === null) {
            return false;
        }

        $origin = $this->normalizeOrigin($request->headers->get('Origin'));
        $referer = $this->normalizeOrigin($request->headers->get('Referer'));

        return $origin === $normalizedTrustedOrigin || $referer === $normalizedTrustedOrigin;
    }

    /**
     * Issue the bootstrap token as an HttpOnly, Secure, SameSite=Strict cookie.
     *
     * This provides an additional layer of security — the cookie cannot be
     * accessed via JavaScript, reducing XSS attack surface.
     *
     * The cookie TTL matches the token's internal TTL (300 seconds).
     */
    public function issueTokenAsCookie(string $token, Request $request): void
    {
        $cookieName = 'widget_bootstrap_token';
        $expiresAt = now()->addSeconds($this->ttlSeconds)->toDateTimeImmutable();

        $request->cookies->set($cookieName, $token);

        // Also set as a response cookie for the next response
        cookie()->queue(
            $cookieName,
            $token,
            (int) ($this->ttlSeconds / 60), // minutes
            '/',
            null, // domain (current)
            request()->secure(), // secure flag
            true, // httpOnly
            false, // raw
            'Strict', // sameSite
        );
    }
}
