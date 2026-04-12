<?php

use App\Http\Middleware\EnforceTenantContext;
use App\Http\Middleware\ResetTenantContext;
use App\Http\Middleware\ResolveTenantFromDomain;
use App\Http\Middleware\SetRequestId;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Symfony\Component\HttpFoundation\Request;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',
        channels: __DIR__ . '/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/auth/login');

        // Register tenant middleware aliases
        $middleware->alias([
            'reset.tenant' => ResetTenantContext::class,
            'resolve.tenant' => ResolveTenantFromDomain::class,
            'set.tenant' => SetTenantContext::class,
            'enforce.tenant' => EnforceTenantContext::class,
            'widget.domain' => \App\Http\Middleware\ValidateWidgetDomain::class,
        ]);


        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook/*',
            'api/widget/*',
        ]);

        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->web(append: [
            SetRequestId::class,
        ]);
        $middleware->api(append: [
            SetRequestId::class,
        ]);

        $middleware->api(append: [
            HandleCors::class,
        ]);
        
        $middleware->append([
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Standardize 429 Too Many Requests responses with Retry-After header
        // and consistent JSON format for all API endpoints.
        $exceptions->respond(function ($response) {
            if ($response->getStatusCode() === 429) {
                // Extract retry-after from the existing response headers if present
                $retryAfter = $response->headers->get('Retry-After', 60);

                // Ensure Retry-After header is set
                $response->headers->set('Retry-After', (string) $retryAfter);

                // Override with standardized JSON format for API requests
                $originalContent = $response->getContent();

                // Only override if the response is JSON or the request expects JSON
                if (
                    str_contains((string) $response->headers->get('Content-Type'), 'json')
                    || str_contains((string) $response->headers->get('Content-Type'), 'application')
                ) {
                    $response->setContent(json_encode([
                        'error' => 'Too Many Requests',
                        'message' => 'Rate limit exceeded. Please retry after the specified time.',
                        'retry_after' => (int) $retryAfter,
                    ], JSON_THROW_ON_ERROR));
                }
            }

            return $response;
        });

        // Reportable exceptions - log with context
        $exceptions->report(function (Throwable $e) {
            // Log Telegram API errors with additional context
            if ($e instanceof \GuzzleHttp\Exception\RequestException) {
                \Illuminate\Support\Facades\Log::error('External API request failed', [
                    'url' => $e->getRequest()->getUri(),
                    'method' => $e->getRequest()->getMethod(),
                    'status' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : null,
                ]);
            }
        });
    })->create();
