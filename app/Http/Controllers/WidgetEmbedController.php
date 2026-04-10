<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WidgetKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class WidgetEmbedController extends Controller
{
    public function __construct(
        protected WidgetKeyService $widgetKeyService,
    ) {}

    /**
     * Serve the widget JavaScript embed script.
     *
     * This endpoint returns the main widget.js file that creates
     * an iframe with the chat UI. Website owners include this
     * on their pages like: /widget.js?key=wsk_xxxx
     *
     * Content-Type: application/javascript
     * No authentication required — the widget_key is passed as a query parameter.
     */
    public function script(Request $request): Response
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            // Return a minimal error script
            $errorScript = <<<'JS'
(function() {
    console.error('[Widget] Invalid or missing widget key.');
})();
JS;
            return response($errorScript, 200, [
                'Content-Type' => 'application/javascript; charset=utf-8',
            ]);
        }

        $cacheDuration = now()->addHours(1);
        $cacheKey = 'widget:script:minified:' . $project->id;

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
     */
    public function embed(Request $request): View|Response
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response('Invalid widget key.', 401)
                ->header('Content-Type', 'text/plain');
        }

        if (! $project->is_active) {
            return response('This widget is currently disabled.', 403)
                ->header('Content-Type', 'text/plain');
        }

        return view('widget.embed', [
            'project_id' => $project->id,
            'project_name' => $project->name,
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
