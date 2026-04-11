<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Idle Conversation Timeout
    |--------------------------------------------------------------------------
    |
    | This value controls how long (in minutes) a conversation can remain
    | idle (no new messages) before it is automatically closed by the
    | scheduled job. Default is 1440 minutes (24 hours).
    |
    */

    'idle_conversation_timeout_minutes' => env('IDLE_CONVERSATION_TIMEOUT_MINUTES', 1440),
];
