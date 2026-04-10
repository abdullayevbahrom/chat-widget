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
        $requiresTrustedSource = ! $request->isMethod('GET') && ! $request->isMethod('HEAD');

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

        if ($requiresTrustedSource) {
            if (! filled($origin)) {
                return $this->deny($request, 'missing_origin_for_write', $project);
            }

            if (! $originAllowed) {
                return $this->deny($request, 'unverified_origin_for_write', $project);
            }

            $request->attributes->set('widget_verified_origin', $this->normalizeOrigin($origin));

            return $next($request);
        }

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

        if ($request->headers->has('X-Widget-Key')) {
            return $this->deny($request, 'unverified_widget_domain', $project);
        }

        return $this->deny($request, 'missing_verified_origin', $project);
    }

    protected function matchesVerifiedOrigin(Project $project, ?string $candidate): bool
    {
        $origin = $this->normalizeOrigin($candidate);

        if ($origin === null) {
            return false;
        }

        return in_array($origin, $this->normalizedVerifiedOrigins($project), true);
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
        return $this->widgetBootstrapService->normalizeOrigin($candidate);
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
