<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Services\WidgetBootstrapService;
use App\Services\WidgetEmbedService;
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
        protected WidgetEmbedService $embedService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Validates the widget key from:
     * 1. HMAC-signed embed script data (data-widget-key, data-signature, etc.)
     * 2. Raw widget key header (X-Widget-Key)
     * 3. Bootstrap token (X-Widget-Bootstrap)
     *
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
        // 1. Try HMAC-signed embed script data
        $embedProject = $this->resolveFromEmbedData($request);
        if ($embedProject !== null) {
            return $embedProject;
        }

        // 2. Try raw widget key
        $key = $request->header('X-Widget-Key');
        if (filled($key)) {
            Log::info('Resolving widget project from raw widget key.', [
                'route' => $request->route()?->getName(),
            ]);

            $request->attributes->set('widget_auth_mode', 'widget_key');

            return $this->widgetKeyService->resolveFromKey($key);
        }

        // 3. Try bootstrap token
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

    /**
     * Resolve project from HMAC-signed embed script data attributes.
     *
     * Expects query parameters or headers:
     * - project_id: The project ID
     * - domain: The domain the widget is embedded on
     * - expires: Unix timestamp when the signature expires
     * - signature: HMAC-SHA256 signature
     */
    protected function resolveFromEmbedData(Request $request): ?Project
    {
        $projectId = $request->input('project_id') ?? $request->header('X-Project-Id');
        $domain = $request->input('domain') ?? $request->header('X-Widget-Domain');
        $expires = $request->input('expires') ?? $request->header('X-Widget-Expires');
        $signature = $request->input('signature') ?? $request->header('X-Widget-Signature');

        if (blank($projectId) || blank($domain) || blank($expires) || blank($signature)) {
            return null;
        }

        // Verify HMAC signature
        if (! $this->embedService->verifyEmbed(
            (int) $projectId,
            (string) $domain,
            (int) $expires,
            (string) $signature,
        )) {
            Log::warning('Rejected widget embed: invalid HMAC signature.', [
                'project_id' => $projectId,
                'domain' => $domain,
                'route' => $request->route()?->getName(),
            ]);

            return null;
        }

        Log::info('Resolving widget project from HMAC-signed embed data.', [
            'project_id' => $projectId,
            'domain' => $domain,
            'route' => $request->route()?->getName(),
        ]);

        $request->attributes->set('widget_auth_mode', 'embed_hmac');
        $request->attributes->set('widget_verified_domain', $domain);

        return Project::query()
            ->whereKey($projectId)
            ->where('domain', $domain)
            ->where('is_active', true)
            ->first();
    }
}
