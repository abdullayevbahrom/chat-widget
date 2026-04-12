<?php

use App\Http\Controllers\Api\AdminConversationController;
use App\Http\Controllers\Api\CspReportController;
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
use App\Http\Middleware\ValidateSanctumTenantScope;
use App\Http\Middleware\ValidateWidgetDomain;
use Illuminate\Support\Facades\Route;

// Tenant-scoped API routes — require authentication + tenant context
Route::middleware(['auth:sanctum', 'set.tenant', 'enforce.tenant', ValidateSanctumTenantScope::class, 'throttle:tenant-api'])
    ->prefix('tenant')
    ->group(function () {
        Route::get('/profile', [TenantProfileController::class, 'show']);
        Route::put('/profile', [TenantProfileController::class, 'update']);

        // Project management
        Route::apiResource('projects', ProjectController::class);
        Route::post('projects/{project}/regenerate-key', [ProjectController::class, 'regenerateKey']);
        Route::post('projects/{project}/revoke-key', [ProjectController::class, 'revokeKey']);
    });

// Telegram Webhook — rate limited, no auth required (Telegram calls this)
// Uses dedicated 'telegram-webhook' rate limiter with IP spoofing protection
Route::middleware(['throttle:telegram-webhook'])
    ->post('telegram/webhook/{tenantSlug}', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

// Widget Bootstrap API — domain validated, returns widget config + conversation state
Route::middleware(['throttle:widget-config', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->get('widget/bootstrap', [WidgetBootstrapController::class, 'bootstrap'])
    ->name('widget.bootstrap');

// Widget Message API — rate limited, widget domain validated
Route::middleware(['throttle:widget-message', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->prefix('widget')
    ->group(function () {
        Route::post('messages', [WidgetMessageController::class, 'store'])->name('widget.messages.store');
        Route::get('messages', [WidgetMessageController::class, 'index'])->name('widget.messages.index');
        Route::get('conversation', [WidgetConversationController::class, 'show'])->name('widget.conversation.show');
        Route::post('conversation/close', [WidgetConversationController::class, 'close'])->name('widget.conversation.close');
    });

// Widget Attachment API — stricter rate limiting due to storage costs
Route::middleware(['throttle:widget-attachment', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->prefix('widget')
    ->group(function () {
        Route::get('attachments/{projectId}/{conversationId}/{fileName}', [WidgetAttachmentController::class, 'download'])
            ->name('widget.attachments.download');
    });

// Admin Conversation API — authenticated, tenant-scoped
Route::middleware(['auth:sanctum', 'set.tenant', 'enforce.tenant', ValidateSanctumTenantScope::class, 'throttle:admin-conversation'])
    ->prefix('tenant')
    ->group(function () {
        Route::apiResource('conversations', AdminConversationController::class)->only(['index', 'show']);
        Route::post('conversations/{conversation}/close', [AdminConversationController::class, 'close'])->name('tenant.conversations.close');
        Route::post('conversations/{conversation}/reopen', [AdminConversationController::class, 'reopen'])->name('tenant.conversations.reopen');
        Route::post('conversations/{conversation}/archive', [AdminConversationController::class, 'archive'])->name('tenant.conversations.archive');
        Route::post('conversations/{conversation}/assign', [AdminConversationController::class, 'assign'])->name('tenant.conversations.assign');
        Route::get('conversations/unread-count', [AdminConversationController::class, 'unreadCount'])->name('tenant.conversations.unread-count');
    });

// CSP Violation Report endpoint — accepts reports from browsers when CSP policies are violated
// Rate limited to prevent abuse; no authentication needed (reports come from browsers)
Route::middleware(['throttle:60,1'])
    ->post('csp-report', [CspReportController::class, 'store'])
    ->name('csp.report.store');

// Widget WebSocket connection endpoint — domain validated
Route::middleware(['throttle:widget-config', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->get('widget/ws/connect', [WidgetMessageController::class, 'wsConnect'])
    ->name('widget.ws.connect');

// Widget WebSocket auth endpoint — validates session for private channel subscription
Route::middleware(['throttle:widget-config', ValidateWidgetDomain::class, ValidateCorsOrigins::class])
    ->post('widget/ws/auth', [WidgetMessageController::class, 'wsAuth'])
    ->name('widget.ws.auth');

// Health Check endpoint — monitoring tool'lar uchun
// IP whitelist orqali himoyalangan; HEALTH_ALLOWED_IPS env da monitoring server IP'larni kiriting
Route::middleware(['throttle:1,1', RestrictHealthEndpoint::class])
    ->get('health', [HealthController::class, 'index'])
    ->name('api.health');
