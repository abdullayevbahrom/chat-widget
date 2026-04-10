<?php

namespace App\Http\Controllers\Api;

use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handle(Request $request, string $tenantSlug): JsonResponse
    {
        // Validate the X-Telegram-Bot-Api-Secret-Token header if configured
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // IP spoofing protection: log the original and forwarded IPs
        $clientIp = $request->ip();
        $forwardedFor = $request->header('X-Forwarded-For');
        $realIp = $request->header('X-Real-IP');

        if ($forwardedFor !== null || $realIp !== null) {
            Log::info('Telegram webhook with proxy headers', [
                'client_ip' => $clientIp,
                'x_forwarded_for' => $forwardedFor,
                'x_real_ip' => $realIp,
                'tenant_slug' => $tenantSlug,
            ]);
        }

        // Find tenant by slug
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if ($tenant === null) {
            Log::warning('Telegram webhook received for unknown tenant', [
                'tenant_slug' => $tenantSlug,
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Tenant not found'], 404);
        }

        // Find the Telegram bot setting for this tenant
        $setting = TelegramBotSetting::where('tenant_id', $tenant->id)->first();

        if ($setting === null) {
            Log::warning('Telegram webhook received for tenant without bot settings', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenantSlug,
            ]);

            return response()->json(['ok' => false, 'error' => 'Bot not configured'], 404);
        }

        // Validate webhook secret token if it is configured
        if ($setting->webhook_secret !== null && $secretToken !== null) {
            // Use hash_equals to prevent timing attacks
            if (! hash_equals($setting->webhook_secret, $secretToken)) {
                Log::warning('Telegram webhook received with invalid secret token', [
                    'tenant_id' => $tenant->id,
                    'setting_id' => $setting->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
            }
        } elseif ($setting->webhook_secret !== null && $secretToken === null) {
            // Secret is configured but not provided in the request
            Log::warning('Telegram webhook received without secret token header', [
                'tenant_id' => $tenant->id,
                'setting_id' => $setting->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        // Check if the bot is active
        if (! $setting->is_active) {
            Log::info('Telegram webhook received for inactive bot', [
                'tenant_id' => $tenant->id,
                'setting_id' => $setting->id,
            ]);

            // Return 200 OK to prevent Telegram from retrying
            return response()->json(['ok' => true, 'result' => true]);
        }

        // Log the incoming webhook payload
        $payload = $request->all();

        Log::info('Telegram webhook received', [
            'tenant_id' => $tenant->id,
            'setting_id' => $setting->id,
            'update_id' => $payload['update_id'] ?? null,
            'has_message' => isset($payload['message']),
            'has_callback_query' => isset($payload['callback_query']),
        ]);

        // TODO: Dispatch the payload to a queue for processing
        // e.g., ProcessTelegramUpdate::dispatch($tenant, $payload);

        // Return 200 OK as expected by Telegram
        return response()->json(['ok' => true, 'result' => true]);
    }
}
