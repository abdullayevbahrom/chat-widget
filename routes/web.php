<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\TenantAuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\WidgetEmbedController;
use App\Http\Middleware\TrackVisitors;
use App\Http\Middleware\ValidateWidgetDomain;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth('tenant_user')->check()) {
        return redirect('/dashboard');
    }
    return view('welcome');
})->name('home');

Route::get('/test-widget', function () {
    return view('test-widget');
})->name('test-widget');

Route::middleware(['web'])->group(function () {
    Route::get('/auth/login', [TenantAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/auth/login', [TenantAuthController::class, 'login']);
    Route::get('/auth/register', [TenantAuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('/auth/register', [TenantAuthController::class, 'register']);
});

Route::middleware(['auth:tenant_user'])->group(function () {
    Route::post('/auth/logout', [TenantAuthController::class, 'logout'])->name('logout');
});

Route::middleware(['web', 'auth:tenant_user'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');

    // Projects CRUD
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::post('/projects/{project}/regenerate-key', [ProjectController::class, 'regenerateKey'])->name('projects.regenerate-key');
    Route::post('/projects/{project}/send-test-message', [ProjectController::class, 'sendTestMessage'])->name('projects.send-test-message');

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
    ->middleware(['throttle:widget-config', TrackVisitors::class, ValidateWidgetDomain::class])
    ->name('widget.embed.view');

Route::get('/widget.js', [WidgetEmbedController::class, 'script'])
    ->middleware(['throttle:widget-config'])
    ->name('widget.embed');

Route::get('/api/widget/config', [WidgetEmbedController::class, 'config'])
    ->middleware(['throttle:widget-config', ValidateWidgetDomain::class])
    ->name('widget.config');

// ==========================================
// Admin Panel Routes (authenticated only, no separate login page)
// ==========================================
Route::prefix('admin')->name('admin.')->middleware(['auth:web'])->group(function () {
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
