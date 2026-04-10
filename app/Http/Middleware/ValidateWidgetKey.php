<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Services\WidgetBootstrapService;
use App\Services\WidgetKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWidgetKey
{
    public function __construct(
        protected WidgetKeyService $widgetKeyService,
        protected WidgetBootstrapService $widgetBootstrapService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Validates the widget key from a request header or a short-lived
     * bootstrap token that is bound to the current request origin.
     * If valid, merges the resolved project into the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $this->resolveProject($request);

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $request->attributes->set('project', $project);

        return $next($request);
    }

    protected function resolveProject(Request $request): ?Project
    {
        $key = $request->header('X-Widget-Key');

        if (filled($key)) {
            Log::info('Resolving widget project from raw widget key.', [
                'route' => $request->route()?->getName(),
            ]);

            $request->attributes->set('widget_auth_mode', 'widget_key');

            return $this->widgetKeyService->resolveFromKey($key);
        }

        $bootstrapToken = $request->header('X-Widget-Bootstrap');
        $bootstrapPayload = $this->widgetBootstrapService->decodeToken($bootstrapToken);

        if ($bootstrapPayload !== null) {
            if (! $this->widgetBootstrapService->requestMatchesTrustedOrigin($request, $bootstrapPayload['trusted_origin'])) {
                Log::warning('Rejected widget bootstrap token because the live request origin did not match.', [
                    'project_id' => $bootstrapPayload['project_id'],
                    'trusted_origin' => $bootstrapPayload['trusted_origin'],
                    'origin' => $request->headers->get('Origin'),
                    'referer' => $request->headers->get('Referer'),
                    'route' => $request->route()?->getName(),
                ]);

                return null;
            }

            Log::info('Resolving widget project from bootstrap token.', [
                'project_id' => $bootstrapPayload['project_id'],
                'trusted_origin' => $bootstrapPayload['trusted_origin'],
                'route' => $request->route()?->getName(),
            ]);

            $request->attributes->set('widget_auth_mode', 'bootstrap_token');
            $request->attributes->set('widget_bootstrap_origin', $bootstrapPayload['trusted_origin']);

            return Project::query()
                ->whereKey($bootstrapPayload['project_id'])
                ->where('is_active', true)
                ->first();
        }

        return null;
    }
}
