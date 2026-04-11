<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Services\WidgetBootstrapService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedWidgetDomain
{
    public function __construct(
        protected WidgetBootstrapService $widgetBootstrapService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return $this->deny($request, 'missing_project_context');
        }

        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');
        $bootstrapOrigin = $request->attributes->get('widget_bootstrap_origin');
        $originAllowed = $this->matchesVerifiedOrigin($project, $origin);
        $refererAllowed = $this->matchesVerifiedOrigin($project, $referer);

        // SECURITY: All requests (including GET/HEAD) must provide a verifiable
        // Origin or Referer header. Previously, GET/HEAD requests could bypass
        // origin verification entirely — this was a security weakness that allowed
        // any client with a valid widget key to access data without domain verification.
        if (is_string($bootstrapOrigin) && $bootstrapOrigin !== '') {
            if (! $this->widgetBootstrapService->requestMatchesTrustedOrigin($request, $bootstrapOrigin)) {
                return $this->deny($request, 'bootstrap_origin_request_mismatch', $project, [
                    'bootstrap_origin' => $bootstrapOrigin,
                ]);
            }

            if (! $this->matchesVerifiedOrigin($project, $bootstrapOrigin)) {
                return $this->deny($request, 'bootstrap_origin_not_verified', $project, [
                    'bootstrap_origin' => $bootstrapOrigin,
                ]);
            }

            $request->attributes->set('widget_verified_origin', $bootstrapOrigin);

            Log::info('Accepted widget request using verified bootstrap origin.', [
                'project_id' => $project->id,
                'route' => $request->route()?->getName(),
                'bootstrap_origin' => $bootstrapOrigin,
                'method' => $request->getMethod(),
            ]);

            return $next($request);
        }

        // All requests must have a verified origin — no method-based bypass.
        // Priority order: Origin header > Referer header > deny.
        if (filled($origin)) {
            if (! $originAllowed) {
                return $this->deny($request, 'unverified_origin', $project);
            }

            $request->attributes->set('widget_verified_origin', $this->normalizeOrigin($origin));

            return $next($request);
        }

        if (filled($referer)) {
            if (! $refererAllowed) {
                return $this->deny($request, 'unverified_referer', $project);
            }

            $request->attributes->set('widget_verified_origin', $this->normalizeOrigin($referer));

            return $next($request);
        }

        // No origin or referer provided — reject the request.
        // This applies to ALL HTTP methods (GET, HEAD, POST, etc.)
        return $this->deny($request, 'missing_verified_origin', $project);
    }

    protected function matchesVerifiedOrigin(Project $project, ?string $candidate): bool
    {
        $origin = $this->normalizeOrigin($candidate);

        if ($origin === null) {
            return false;
        }

        $verifiedOrigins = $this->normalizedVerifiedOrigins($project);

        // Direct match check
        if (in_array($origin, $verifiedOrigins, true)) {
            return true;
        }

        // Wildcard pattern matching: e.g., *.example.com matches sub.example.com
        return $this->matchesWildcardOrigin($origin, $verifiedOrigins);
    }

    /**
     * Check if an origin matches any wildcard pattern in the verified origins list.
     *
     * Wildcard patterns are stored as domains with a leading "*." prefix,
     * e.g., "https://*.example.com" matches "https://sub.example.com" and
     * "https://a.b.example.com" but NOT "https://example.com" itself.
     *
     * @param  string  $origin  The normalized candidate origin.
     * @param  array<int, string>  $verifiedOrigins  List of normalized verified origins.
     */
    protected function matchesWildcardOrigin(string $origin, array $verifiedOrigins): bool
    {
        $candidateParts = parse_url($origin);

        if ($candidateParts === false || ! isset($candidateParts['host'])) {
            return false;
        }

        $candidateHost = strtolower($candidateParts['host']);
        $candidateScheme = $candidateParts['scheme'] ?? null;
        $candidatePort = $candidateParts['port'] ?? null;

        foreach ($verifiedOrigins as $verifiedOrigin) {
            $verifiedParts = parse_url($verifiedOrigin);

            if ($verifiedParts === false || ! isset($verifiedParts['host'])) {
                continue;
            }

            $verifiedHost = strtolower($verifiedParts['host']);

            // Check if the verified host is a wildcard pattern
            if (! str_starts_with($verifiedHost, '*.')) {
                continue;
            }

            // Wildcard domain: *.example.com
            $wildcardBase = substr($verifiedHost, 2); // Remove "*." prefix

            // The candidate must end with ".{wildcardBase}" to match
            // e.g., sub.example.com matches *.example.com
            // but example.com does NOT match *.example.com
            if (! str_ends_with($candidateHost, '.'.$wildcardBase)) {
                continue;
            }

            // Scheme must match exactly
            if (($verifiedParts['scheme'] ?? null) !== $candidateScheme) {
                continue;
            }

            // Port must match exactly
            $verifiedPort = $verifiedParts['port'] ?? null;

            if ($verifiedPort !== $candidatePort) {
                continue;
            }

            // Only allow wildcards for verified domains (already ensured by
            // checking against getVerifiedDomainsCache which only returns
            // domains where isVerified() === true)
            return true;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizedVerifiedOrigins(Project $project): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $domain): ?string => is_string($domain) ? $this->normalizeOrigin($domain) : null,
            $project->getVerifiedDomainsCache(),
        ))));
    }

    protected function normalizeOrigin(?string $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        // Reject the literal string "null" — some browsers send this for
        // sandboxed iframes or privacy modes. It must not be treated as a
        // valid origin because it cannot be verified against a domain list.
        $trimmed = trim($candidate);

        if (strtolower($trimmed) === 'null') {
            return null;
        }

        $normalized = $this->widgetBootstrapService->normalizeOrigin($candidate);

        // Additional strict validation: require scheme and host to be present.
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'];

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // Port validation: if present, must be a valid integer.
        if (isset($parts['port']) && (! is_int($parts['port']) || $parts['port'] < 1 || $parts['port'] > 65535)) {
            return null;
        }

        return $normalized;
    }

    protected function deny(Request $request, string $reason, ?Project $project = null, array $context = []): Response
    {
        Log::warning('Rejected widget request from unverified domain.', [
            'project_id' => $project?->id,
            'route' => $request->route()?->getName(),
            'method' => $request->getMethod(),
            'reason' => $reason,
            'has_origin' => $request->headers->has('Origin'),
            'has_referer' => $request->headers->has('Referer'),
            'normalized_origin' => $this->normalizeOrigin($request->headers->get('Origin')),
            'normalized_referer' => $this->normalizeOrigin($request->headers->get('Referer')),
            'bootstrap_origin' => $request->attributes->get('widget_bootstrap_origin'),
            ...$context,
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'Widget domain is not authorized.'], 403);
        }

        return response('Widget domain is not authorized.', 403)
            ->header('Content-Type', 'text/plain');
    }
}
