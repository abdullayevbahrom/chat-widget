<?php

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectDomainController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\TenantDomainController;
use App\Http\Controllers\Api\TenantProfileController;
use App\Http\Controllers\Api\WidgetMessageController;
use App\Http\Middleware\ValidateWidgetKey;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('tenant')->group(function () {
    Route::get('/profile', [TenantProfileController::class, 'show']);
    Route::put('/profile', [TenantProfileController::class, 'update']);
    Route::apiResource('domains', TenantDomainController::class);

    // Project management
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/regenerate-key', [ProjectController::class, 'regenerateKey']);
    Route::post('projects/{project}/revoke-key', [ProjectController::class, 'revokeKey']);

    // Project domain management
    Route::apiResource('project-domains', ProjectDomainController::class);
    Route::post('project-domains/{domain}/verify', [ProjectDomainController::class, 'verify']);
});

// Telegram Webhook — rate limited, no auth required (Telegram calls this)
// Uses dedicated 'telegram-webhook' rate limiter with IP spoofing protection
Route::middleware(['throttle:telegram-webhook'])
    ->post('telegram/webhook/{tenantSlug}', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

// Widget Message API — rate limited, widget key validated
Route::middleware(['throttle:widget-message', ValidateWidgetKey::class])
    ->prefix('widget')
    ->group(function () {
        Route::post('messages', [WidgetMessageController::class, 'store'])->name('widget.messages.store');
        Route::get('messages', [WidgetMessageController::class, 'index'])->name('widget.messages.index');
    });
