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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_methods' => ['*'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
