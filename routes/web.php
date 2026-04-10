<?php

use App\Http\Controllers\WidgetEmbedController;
use App\Http\Middleware\ValidateWidgetKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Widget embed endpoints — public, no auth required
// Widget key is validated via ValidateWidgetKey middleware for config endpoint
Route::get('/widget.js', [WidgetEmbedController::class, 'script'])
    ->name('widget.embed');

Route::get('/api/widget/config', [WidgetEmbedController::class, 'config'])
    ->middleware(['throttle:widget-config', ValidateWidgetKey::class])
    ->name('widget.config');

// Tenant-specific routes (accessible by tenant users)
Route::middleware(['auth:tenant_user', 'web'])->group(function () {
    // Additional tenant routes can be added here
});
