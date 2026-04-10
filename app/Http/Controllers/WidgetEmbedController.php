<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WidgetKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class WidgetEmbedController extends Controller
{
    public function __construct(
        protected WidgetKeyService $widgetKeyService,
    ) {}

    /**
     * Serve the widget JavaScript embed script.
     *
     * This endpoint returns a minimal JavaScript bootstrapper that
     * fetches the widget configuration from the /api/widget/config endpoint
     * and initializes the widget on the page.
     *
     * Content-Type: application/javascript
     * No authentication required — the widget_key is passed as a query parameter.
     */
    public function script(Request $request): Response
    {
        $cacheDuration = now()->addHours(1);

        return Cache::remember('widget:embed:script', $cacheDuration, function () {
            $content = <<<'JS'
(function() {
    'use strict';

    var scriptEl = document.currentScript;
    if (!scriptEl) {
        var scripts = document.getElementsByTagName('script');
        scriptEl = scripts[scripts.length - 1];
    }

    var src = scriptEl.src;
    var params = new URL(src).searchParams;
    var widgetKey = params.get('key') || params.get('widget_key');

    if (!widgetKey) {
        console.error('[Widget] Missing widget_key parameter.');
        return;
    }

    var configUrl = '/api/widget/config?key=' + encodeURIComponent(widgetKey);

    fetch(configUrl)
        .then(function(r) {
            if (!r.ok) {
                throw new Error('Widget config fetch failed: ' + r.status);
            }
            return r.json();
        })
        .then(function(config) {
            window.__WIDGET_CONFIG__ = config;

            // Dispatch custom event so host page can react
            window.dispatchEvent(new CustomEvent('widget:ready', { detail: config }));
        })
        .catch(function(err) {
            console.error('[Widget] Error loading config:', err.message);
        });
})();
JS;

            return response($content, 200, [
                'Content-Type' => 'application/javascript; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        });
    }

    /**
     * Return widget configuration as JSON.
     *
     * Authenticated via widget_key query parameter (validated by ValidateWidgetKey middleware).
     * Rate limited to prevent abuse.
     */
    public function config(Request $request): JsonResponse
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json([
                'error' => 'Invalid or missing widget key.',
            ], 401);
        }

        if (! $project->is_active) {
            return response()->json([
                'error' => 'This widget is currently disabled.',
            ], 403);
        }

        return response()->json([
            'project_name' => $project->name,
            'project_id' => $project->id,
            'settings' => [
                'theme' => $project->getWidgetSetting('theme', 'light'),
                'position' => $project->getWidgetSetting('position', 'bottom-right'),
                'width' => $project->getWidgetSetting('width', 350),
                'height' => $project->getWidgetSetting('height', 500),
                'primary_color' => $project->getWidgetSetting('primary_color', '#3B82F6'),
                'custom_css' => $project->getWidgetSetting('custom_css', null),
            ],
            'verified_domains' => $project->getVerifiedDomainsCache(),
        ]);
    }
}
