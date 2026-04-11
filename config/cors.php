<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines which origins, methods, and headers are
    | allowed for cross-origin requests. It is specifically tailored for
    | the widget API endpoints.
    |
    | SECURITY NOTE: In production, REVERB_ALLOWED_ORIGINS (or a dedicated
    | CORS_ALLOWED_ORIGINS env variable) should contain only the specific
    | domains that need to access the widget API. Never use '*' in production.
    | Wildcard origins are automatically rejected and will result in an empty
    | allowed_origins list (see ValidateCorsOrigins middleware).
    |
    */

    // Applied to /api/widget/* routes only.
    // Other API routes use the default Laravel CORS behavior.

    'paths' => ['api/widget/*'],

    // Dynamically loaded from REVERB_ALLOWED_ORIGINS env variable.
    // These are the only origins allowed to make cross-origin requests
    // to widget endpoints. Wildcard (*) values are filtered out.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('REVERB_ALLOWED_ORIGINS', ''))),
        static fn (string $origin): bool => $origin !== '' && $origin !== '*' && ! str_contains($origin, '*')
    )),

    // If no origins are configured, fall back to localhost for development.
    'allowed_origins_patterns' => [],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_headers' => [
        'Content-Type',
        'X-Widget-Key',
        'X-Widget-Bootstrap',
        'Authorization',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Retry-After',
    ],

    'max_age' => 3600,

    'supports_credentials' => true,

];
