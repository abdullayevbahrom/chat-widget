<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\TenantAuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\TenantDomainController;
use App\Http\Controllers\TenantProfileController;
use App\Http\Controllers\WidgetEmbedController;
use App\Http\Middleware\EnsureVerifiedWidgetDomain;
use App\Http\Middleware\TrackVisitors;
use App\Http\Middleware\ValidateWidgetKey;
use Illuminate\Support\Facades\Route;

// ==========================================
// Landing Page
// ==========================================
Route::get('/', function () {
    if (auth('tenant_user')->check()) {
        return redirect('/dashboard');
    }
    return view('welcome');
})->name('home');

// ==========================================
// Tenant Auth Routes
// ==========================================
Route::prefix('auth')->name('tenant.')->middleware('guest:tenant_user')->group(function () {
    Route::get('/login', [TenantAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [TenantAuthController::class, 'login']);
    Route::get('/register', [TenantAuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [TenantAuthController::class, 'register']);
});

Route::post('/auth/logout', [TenantAuthController::class, 'logout'])
    ->middleware(['auth:tenant_user'])
    ->name('tenant.logout');

// ==========================================
// Tenant Dashboard Routes
// ==========================================
Route::middleware(['web', 'reset.tenant', 'auth:tenant_user', 'set.tenant'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Projects CRUD
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::post('/projects/{project}/regenerate-key', [ProjectController::class, 'regenerateKey'])->name('projects.regenerate-key');

    // Domains CRUD
    Route::resource('tenant-domains', TenantDomainController::class)
        ->parameters(['tenant-domains' => 'domain'])
        ->except(['show'])
        ->names('domains');
    Route::post('/tenant-domains/{domain}/verify', [TenantDomainController::class, 'verify'])->name('domains.verify');
    Route::post('/tenant-domains/{domain}/reverify', [TenantDomainController::class, 'reverify'])->name('domains.reverify');

    // Tenant Profile
    Route::get('/tenant-profile', [TenantProfileController::class, 'index'])->name('profile');
    Route::put('/tenant-profile', [TenantProfileController::class, 'update'])->name('profile.update');

    // Telegram Bot Settings
    Route::get('/telegram-bot-settings', [TelegramBotController::class, 'index'])->name('telegram');
    Route::put('/telegram-bot-settings', [TelegramBotController::class, 'update'])->name('telegram.update');
    Route::post('/telegram-bot-settings/test-message', [TelegramBotController::class, 'testMessage'])->name('telegram.test-message');
    Route::delete('/telegram-bot-settings/delete-webhook', [TelegramBotController::class, 'deleteWebhook'])->name('telegram.delete-webhook');

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::patch('/conversations/{conversation}/close', [ConversationController::class, 'close'])->name('conversations.close');
    Route::patch('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen'])->name('conversations.reopen');
    Route::patch('/conversations/{conversation}/archive', [ConversationController::class, 'archive'])->name('conversations.archive');
});

// ==========================================
// Widget Embed Endpoints
// ==========================================
Route::get('/widget/embed', [WidgetEmbedController::class, 'embed'])
    ->middleware(['throttle:widget-config', TrackVisitors::class, ValidateWidgetKey::class, EnsureVerifiedWidgetDomain::class])
    ->name('widget.embed.view');

Route::get('/widget.js', [WidgetEmbedController::class, 'script'])
    ->middleware(['throttle:widget-config'])
    ->name('widget.embed');

Route::get('/api/widget/config', [WidgetEmbedController::class, 'config'])
    ->middleware(['throttle:widget-config', ValidateWidgetKey::class, EnsureVerifiedWidgetDomain::class])
    ->name('widget.config');

// ==========================================
// Admin Panel Routes
// ==========================================
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest routes
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
    });

    // Authenticated admin routes
    Route::middleware(['auth:web'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('manage-tenants', AdminTenantController::class)
            ->parameters(['manage-tenants' => 'tenant'])
            ->except(['show'])
            ->names('tenants');
        Route::resource('manage-users', AdminUserController::class)
            ->parameters(['manage-users' => 'user'])
            ->except(['show'])
            ->names('users');
    });
});
