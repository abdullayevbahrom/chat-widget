<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WidgetBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class WidgetEmbedController extends Controller
{
    public function __construct(
        protected WidgetBootstrapService $widgetBootstrapService,
    ) {}

    /**
     * Serve the widget JavaScript embed script.
     *
     * This endpoint returns the public widget SDK bundle.
     * Domain validation is done via the ValidateWidgetDomain middleware
     * which checks the Origin/Referer header against the projects table.
     *
     * Content-Type: application/javascript
     * No authentication required - domain validated via middleware.
     */
    public function script(Request $request): Response
    {
        $cacheDuration = now()->addHours(1);
        $cacheKey = 'widget:script:minified';

        $content = Cache::remember($cacheKey, $cacheDuration, function () {
            $jsPath = public_path('js/widget.js');

            if (file_exists($jsPath)) {
                return file_get_contents($jsPath);
            }

            // Fallback inline JavaScript if file doesn't exist
            return $this->getFallbackWidgetScript();
        });

        return response($content, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Render the widget iframe content.
     *
     * This view is loaded inside an iframe to provide isolation
     * from the host page styles. Includes the full chat UI.
     *
     * Domain validation is handled by ValidateWidgetDomain middleware.
     */
    public function embed(Request $request): View|Response
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response('Invalid or unregistered domain.', 401)
                ->header('Content-Type', 'text/plain');
        }

        if (! $project->is_active) {
            return response('This widget is currently disabled.', 403)
                ->header('Content-Type', 'text/plain');
        }

        $trustedOrigin = $request->attributes->get('widget_origin');

        if (! is_string($trustedOrigin) || $trustedOrigin === '') {
            return response('Widget origin could not be verified.', 403)
                ->header('Content-Type', 'text/plain');
        }

        $bootstrapToken = $this->widgetBootstrapService->issueToken($project, $trustedOrigin);

        // Generate a CSP nonce for this request — unique per response
        // to prevent XSS via inline scripts/styles
        $cspNonce = base64_encode(random_bytes(16));

        $response = response(view('widget.embed', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'bootstrap_token' => $bootstrapToken,
            'trusted_origin' => $trustedOrigin,
            'csp_nonce' => $cspNonce,
            'settings' => [
                'theme' => $project->getWidgetSetting('theme', 'dark'),
                'position' => $project->getWidgetSetting('position', 'bottom-right'),
                'width' => $project->getWidgetSetting('width', 360),
                'height' => $project->getWidgetSetting('height', 520),
                'primary_color' => $project->getWidgetSetting('primary_color', '#8B5CF6'),
                'custom_css' => $project->getWidgetSetting('custom_css', null),
                'privacy_policy_url' => $project->getWidgetSetting('privacy_policy_url', ''),
                'chat_name' => $project->getWidgetSetting('chat_name', $project->name),
            ],
        ]));

        // CSP via HTTP header (instead of meta tag in HTML)
        // SECURITY: Uses nonce-based script-src and style-src instead of 'unsafe-inline'
        // to prevent XSS attacks. Each response gets a unique nonce that is
        // injected into <script> and <style> tags.
        //
        // frame-ancestors: restrict which origins can embed this page in an iframe
        // connect-src: allow connection to Reverb WebSocket host
        // report-uri: send CSP violations to our reporting endpoint
        $reverbHost = parse_url(config('app.url'), PHP_URL_HOST);
        $reverbPort = config('broadcasting.connections.reverb.port', 6001);
        $reverbScheme = config('broadcasting.connections.reverb.options.tls', false) ? 'wss' : 'ws';
        $appUrl = rtrim(config('app.url'), '/');

        $response->header('Content-Security-Policy', "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'nonce-{$cspNonce}'; style-src 'self' https://cdn.jsdelivr.net 'nonce-{$cspNonce}'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self' {$reverbScheme}://{$reverbHost}:{$reverbPort}; media-src 'self' blob:; object-src 'none'; frame-src 'none'; frame-ancestors {$trustedOrigin}; base-uri 'none'; form-action 'self'; report-uri {$appUrl}/api/csp-report;");

        return $response;
    }

    /**
     * Return widget configuration as JSON.
     *
     * Domain validated via ValidateWidgetDomain middleware.
     * Note: The preferred entry point is now GET /api/widget/bootstrap
     * which returns config + conversation + messages in one call.
     * This endpoint is kept for backward compatibility.
     */
    public function config(Request $request): JsonResponse
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or unregistered domain.',
            ], 401);
        }

        if (! $project->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'This widget is currently disabled.',
            ], 403);
        }

        $payload = [
            'project_name' => $project->name,
            'project_id' => $project->id,
            'settings' => [
                'theme' => $project->getWidgetSetting('theme', 'dark'),
                'position' => $project->getWidgetSetting('position', 'bottom-right'),
                'width' => $project->getWidgetSetting('width', 360),
                'height' => $project->getWidgetSetting('height', 520),
                'primary_color' => $project->getWidgetSetting('primary_color', '#8B5CF6'),
                'custom_css' => $project->getWidgetSetting('custom_css', null),
                'privacy_policy_url' => $project->getWidgetSetting('privacy_policy_url', ''),
                'chat_name' => $project->getWidgetSetting('chat_name', $project->name),
            ],
            'websocket' => [
                'enabled' => config('broadcasting.default') === 'reverb',
                'endpoint' => route('widget.ws.connect', [], false),
                'host' => parse_url(config('app.url'), PHP_URL_HOST),
                'port' => request()->secure() ? 443 : 80,
                'secure' => request()->secure(),
            ],
        ];

        $trustedOrigin = $request->attributes->get('widget_origin');

        if (is_string($trustedOrigin) && $trustedOrigin !== '') {
            $payload['bootstrap_token'] = $this->widgetBootstrapService->issueToken($project, $trustedOrigin);
            $payload['trusted_origin'] = $trustedOrigin;
        }

        return response()->json($payload);
    }

    /**
     * Get fallback inline widget script if the built file doesn't exist.
     *
     * This ensures the widget still works even if the build step fails.
     */
    protected function getFallbackWidgetScript(): string
    {
        return <<<'JS'
(function() {
    'use strict';
    console.error('[Widget] widget.js file not found. Please run npm run build.');
})();
JS;
    }
}
