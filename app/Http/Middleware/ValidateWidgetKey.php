<?php

namespace App\Http\Middleware;

use App\Services\WidgetKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWidgetKey
{
    public function __construct(
        protected WidgetKeyService $widgetKeyService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Validates the widget key from query parameter or header.
     * If valid, merges the resolved project into the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->input('widget_key')
            ?? $request->input('key')
            ?? $request->header('X-Widget-Key');

        if (! $key || ! $this->widgetKeyService->validateKey($key)) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $project = $this->widgetKeyService->resolveFromKey($key);
        $request->merge(['project' => $project]);

        return $next($request);
    }
}
