<?php

namespace App\Providers;

use App\Listeners\ActivateTenantOnEmailVerified;
use App\Models\Project;
use App\Models\ProjectDomain;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Policies\ProjectDomainPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TelegramBotSettingPolicy;
use App\Policies\TenantDomainPolicy;
use App\Policies\TenantPolicy;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(TenantDomain::class, TenantDomainPolicy::class);
        Gate::policy(TelegramBotSetting::class, TelegramBotSettingPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectDomain::class, ProjectDomainPolicy::class);

        // Auto-activate tenant when email is verified
        Event::listen(
            Verified::class,
            ActivateTenantOnEmailVerified::class,
        );

        // Explicit API rate limit configuration
        // 60 requests per minute for authenticated users
        // 20 requests per minute for guests
        RateLimiter::for('api', function ($request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->getAuthIdentifier())
                : Limit::perMinute(20)->by($request->ip());
        });

        // Separate rate limit for registration endpoints
        RateLimiter::for('tenant-registration', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for Telegram webhook with IP spoofing protection
        // Uses X-Forwarded-For validation and per-tenant limiting
        RateLimiter::for('telegram-webhook', function ($request) {
            // Use the real client IP, accounting for trusted proxies
            $clientIp = $request->ip();

            // Also rate limit by tenant slug to prevent abuse targeting a specific tenant
            $tenantSlug = $request->route('tenantSlug');
            $tenantKey = $tenantSlug !== null ? "tenant:{$tenantSlug}" : 'unknown';

            return [
                // Global IP-based limit: 120 requests per minute
                Limit::perMinute(120)->by("ip:{$clientIp}"),
                // Per-tenant limit: 60 requests per minute
                Limit::perMinute(60)->by($tenantKey),
            ];
        });

        // Rate limiter for widget config endpoint
        // Limited by widget key to prevent abuse of a specific widget
        RateLimiter::for('widget-config', function (Request $request) {
            $widgetKey = $request->header('X-Widget-Key')
                ?? $request->header('X-Widget-Bootstrap')
                ?? $request->query('project')
                ?? $request->ip();

            return Limit::perMinute(60)->by("widget:{$widgetKey}");
        });

        // Rate limiter for widget message sending
        // 30 messages per minute per widget key
        RateLimiter::for('widget-message', function (Request $request) {
            $widgetKey = $request->header('X-Widget-Key')
                ?? $request->header('X-Widget-Bootstrap')
                ?? $request->ip();

            return Limit::perMinute(30)->by("widget-msg:{$widgetKey}");
        });
    }
}
