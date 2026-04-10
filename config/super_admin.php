<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin Credentials
    |--------------------------------------------------------------------------
    |
    | These values are used by the SuperAdminSeeder to create the initial
    | super admin account. Always set strong passwords in production.
    |
    | IMPORTANT: SUPER_ADMIN_PASSWORD should be set in your .env file.
    | If missing, returns null gracefully — the seeder will handle validation.
    |
    */

    'email' => env('SUPER_ADMIN_EMAIL'),
    'password' => env('SUPER_ADMIN_PASSWORD'),
];
