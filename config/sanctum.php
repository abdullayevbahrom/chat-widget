<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should be your localhost
    | and frontend application domains.
    |
    | For production, set STATEFUL_DOMAINS in your .env file to include
    | only the specific domains that need cookie-based authentication.
    |
    */

    'stateful' => explode(',', env('STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Routes Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix is used for Sanctum's built-in authentication routes
    | (e.g., /sanctum/csrf-cookie).
    |
    */

    'prefix' => 'sanctum',

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware used by Sanctum to ensure stateful requests.
    | 'EnsureFrontendRequestsAreStateful' handles cookie-based auth for SPAs.
    |
    */

    'middleware' => [
        'verify_csrf_token' => VerifyCsrfToken::class,
        'encrypt_cookies' => EncryptCookies::class,
        'ensure_front_end_requests_are_stateful' => EnsureFrontendRequestsAreStateful::class,
    ],

];
