<?php

use App\Http\Controllers\WidgetEmbedController;
use App\Http\Middleware\EnsureVerifiedWidgetDomain;
use App\Http\Middleware\ValidateWidgetKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Widget embed endpoints — the SDK bundle is public, runtime requests use widget headers.
Route::get('/widget/embed', [WidgetEmbedController::class, 'embed'])
    ->middleware(['throttle:widget-config', ValidateWidgetKey::class, EnsureVerifiedWidgetDomain::class])
    ->name('widget.embed.view');

Route::get('/widget.js', [WidgetEmbedController::class, 'script'])
    ->middleware(['throttle:widget-config'])
    ->name('widget.embed');

Route::get('/api/widget/config', [WidgetEmbedController::class, 'config'])
    ->middleware(['throttle:widget-config', ValidateWidgetKey::class, EnsureVerifiedWidgetDomain::class])
    ->name('widget.config');

// Tenant-specific routes (accessible by tenant users)
Route::middleware(['auth:tenant_user', 'web'])->group(function () {
    // Additional tenant routes can be added here
});
