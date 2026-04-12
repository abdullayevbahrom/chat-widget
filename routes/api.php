<?php

use App\Http\Controllers\Api\AdminConversationController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\TenantProfileController;
use App\Http\Controllers\Api\WidgetAttachmentController;
use App\Http\Controllers\Api\WidgetBootstrapController;
use App\Http\Controllers\Api\WidgetConversationController;
use App\Http\Controllers\Api\WidgetMessageController;
use App\Http\Middleware\RestrictHealthEndpoint;
use App\Http\Middleware\ValidateCorsOrigins;
use App\Http\Middleware\ValidateWidgetDomain;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:telegram-webhook'])
    ->post('projects/{project}/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook.project');

Route::middleware(['auth:sanctum'])
    ->prefix('tenant')
    ->group(function () {
        Route::get('/profile', [TenantProfileController::class, 'show']);
        Route::put('/profile', [TenantProfileController::class, 'update']);

        Route::apiResource('projects', ProjectController::class);
        Route::post('projects/{project}/regenerate-key', [ProjectController::class, 'regenerateKey']);
        Route::post('projects/{project}/revoke-key', [ProjectController::class, 'revokeKey']);
    });

Route::middleware(['throttle:widget-config', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->get('widget/bootstrap', [WidgetBootstrapController::class, 'bootstrap'])
    ->name('widget.bootstrap');

Route::middleware(['throttle:widget-message', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->prefix('widget')
    ->group(function () {
        Route::get('conversations', [WidgetConversationController::class, 'index'])->name('widget.conversations.index');
        Route::post('messages', [WidgetMessageController::class, 'store'])->name('widget.messages.store');
        Route::get('messages', [WidgetMessageController::class, 'index'])->name('widget.messages.index');
        Route::get('conversation', [WidgetConversationController::class, 'show'])->name('widget.conversation.show');
        Route::post('conversation/close', [WidgetConversationController::class, 'close'])->name('widget.conversation.close');
    });

Route::middleware(['throttle:widget-attachment', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->prefix('widget')
    ->group(function () {
        Route::get('attachments/{projectId}/{conversationId}/{fileName}', [WidgetAttachmentController::class, 'download'])
            ->name('widget.attachments.download');
    });

Route::middleware(['auth:sanctum'])
    ->prefix('tenant')
    ->group(function () {
        Route::apiResource('conversations', AdminConversationController::class)->only(['index', 'show']);
        Route::post('conversations/{conversation}/close', [AdminConversationController::class, 'close'])->name('tenant.conversations.close');
        Route::post('conversations/{conversation}/reopen', [AdminConversationController::class, 'reopen'])->name('tenant.conversations.reopen');
        Route::post('conversations/{conversation}/archive', [AdminConversationController::class, 'archive'])->name('tenant.conversations.archive');
        Route::post('conversations/{conversation}/assign', [AdminConversationController::class, 'assign'])->name('tenant.conversations.assign');
        Route::get('conversations/unread-count', [AdminConversationController::class, 'unreadCount'])->name('tenant.conversations.unread-count');
    });

Route::middleware(['throttle:widget-config', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->get('widget/ws/connect', [WidgetMessageController::class, 'wsConnect'])
    ->name('widget.ws.connect');

Route::middleware(['throttle:widget-config', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->post('widget/ws/auth', [WidgetMessageController::class, 'wsAuth'])
    ->name('widget.ws.auth');

Route::middleware(['throttle:1,1', RestrictHealthEndpoint::class])
    ->get('health', [HealthController::class, 'index'])
    ->name('api.health');
