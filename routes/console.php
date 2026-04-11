<?php

use App\Jobs\CloseIdleConversations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean up old visitor records weekly
Schedule::command('visitor:cleanup')->weekly();

// Close idle conversations every hour
Schedule::job(new CloseIdleConversations)->hourly();
