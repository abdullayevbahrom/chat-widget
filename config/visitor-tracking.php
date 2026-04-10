<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Visitor Tracking Middleware Groups
    |--------------------------------------------------------------------------
    |
    | The middleware groups where visitor tracking should be enabled.
    | By default, only the 'web' group is tracked (not API routes).
    |
    */
    'middleware_groups' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Cleanup After Days
    |--------------------------------------------------------------------------
    |
    | Number of days to keep visitor records before they are cleaned up.
    | Old records are removed by the visitor:cleanup command.
    |
    */
    'cleanup_after_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Ignore Extensions
    |--------------------------------------------------------------------------
    |
    | File extensions that should not be tracked (static assets).
    | Requests for these file types will be ignored by the middleware.
    |
    */
    'ignore_extensions' => [
        'js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg',
        'woff', 'woff2', 'ttf', 'eot', 'map',
    ],

    /*
    |--------------------------------------------------------------------------
    | Track Bots
    |--------------------------------------------------------------------------
    |
    | Whether to track bot/crawler visits. When false, requests from
    | known bots and crawlers will be ignored.
    |
    */
    'track_bots' => false,
];
