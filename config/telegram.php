<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot API Base URL
    |--------------------------------------------------------------------------
    |
    | This value is the base URL for all Telegram Bot API requests.
    | You may change this to use a custom Telegram Bot API server.
    |
    */

    'bot_api_base_url' => env('TELEGRAM_BOT_API_BASE_URL', 'https://api.telegram.org/bot'),

    /*
    |--------------------------------------------------------------------------
    | Admin User IDs
    |--------------------------------------------------------------------------
    |
    | Telegram user IDs that should receive error notifications.
    | Comma-separated list of user IDs.
    |
    */

    'admin_user_ids' => array_filter(
        array_map('trim', explode(',', (string) env('TELEGRAM_ADMIN_USER_IDS', '')))
    ),
];
