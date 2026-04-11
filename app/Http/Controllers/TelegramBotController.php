<?php

namespace App\Http\Controllers;

use App\Models\TelegramBotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TelegramBotController extends Controller
{
    /**
     * Display the Telegram bot settings page.
     */
    public function index(): View
    {
        $user = Auth::guard('tenant_user')->user();
        $settings = TelegramBotSetting::firstOrNew(['tenant_id' => $user->tenant_id]);

        $botToken = $settings->bot_token;
        $maskedToken = $botToken ? $this->maskToken($botToken) : '';

        return view('tenant.telegram-bot', compact('settings', 'maskedToken', 'botToken'));
    }

    /**
     * Update the Telegram bot settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::guard('tenant_user')->user();

        $settings = TelegramBotSetting::firstOrNew(['tenant_id' => $user->tenant_id]);

        $validated = $request->validate([
            'bot_token' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'bot_username' => ['nullable', 'string', 'max:100'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Only update bot_token if a new value is provided (not masked)
        if (! empty($validated['bot_token']) && $validated['bot_token'] !== str_repeat('*', strlen($validated['bot_token']))) {
            $settings->bot_token = $validated['bot_token'];

            // Fetch bot info from Telegram API
            $botInfo = $this->fetchBotInfo($validated['bot_token']);
            if ($botInfo) {
                $settings->bot_username = '@'.$botInfo['username'];
                $settings->bot_name = $botInfo['first_name'];
            }
        }

        $settings->webhook_url = $validated['webhook_url'] ?? $settings->webhook_url;
        $settings->bot_username = $validated['bot_username'] ?? $settings->bot_username;
        $settings->bot_name = $validated['bot_name'] ?? $settings->bot_name;
        $settings->chat_id = $validated['chat_id'] ?? $settings->chat_id;
        $settings->is_active = $request->boolean('is_active', false);

        $settings->save();

        // Set webhook if URL provided
        if (! empty($validated['webhook_url'])) {
            $this->setWebhook($settings);
        }

        return redirect()
            ->route('dashboard.telegram')
            ->with('success', 'Telegram bot settings updated successfully.');
    }

    /**
     * Send a test message to the configured chat.
     */
    public function testMessage(): JsonResponse
    {
        $user = Auth::guard('tenant_user')->user();
        $settings = TelegramBotSetting::where('tenant_id', $user->tenant_id)->first();

        if (! $settings || ! $settings->bot_token) {
            return response()->json([
                'success' => false,
                'message' => 'Bot token is not configured.',
            ], 400);
        }

        if (! $settings->chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'Chat ID is not configured.',
            ], 400);
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$settings->bot_token}/sendMessage", [
            'chat_id' => $settings->chat_id,
            'text' => "✅ Test message from ChatWidget\n\nThis is a test message to verify your Telegram bot integration is working correctly.\n\nTime: ".now()->format('Y-m-d H:i:s'),
            'parse_mode' => 'HTML',
        ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'message' => 'Test message sent successfully!',
            ]);
        }

        $errorData = $response->json();
        $errorMessage = $errorData['description'] ?? 'Failed to send test message.';

        return response()->json([
            'success' => false,
            'message' => $errorMessage,
        ], 400);
    }

    /**
     * Delete the webhook URL.
     */
    public function deleteWebhook(): RedirectResponse
    {
        $user = Auth::guard('tenant_user')->user();
        $settings = TelegramBotSetting::where('tenant_id', $user->tenant_id)->first();

        if (! $settings || ! $settings->bot_token) {
            return redirect()
                ->route('dashboard.telegram')
                ->with('error', 'Bot token is not configured.');
        }

        // Delete webhook from Telegram
        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$settings->bot_token}/deleteWebhook", [
            'drop_pending_updates' => true,
        ]);

        // Clear webhook URL from settings
        $settings->webhook_url = null;
        $settings->last_webhook_status = $response->successful() ? 'deleted' : 'failed';
        $settings->save();

        if ($response->successful()) {
            return redirect()
                ->route('dashboard.telegram')
                ->with('success', 'Webhook deleted successfully.');
        }

        return redirect()
            ->route('dashboard.telegram')
            ->with('warning', 'Webhook URL cleared from settings, but failed to delete from Telegram API.');
    }

    /**
     * Set webhook via Telegram API.
     */
    protected function setWebhook(TelegramBotSetting $settings): void
    {
        if (empty($settings->bot_token) || empty($settings->webhook_url)) {
            return;
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$settings->bot_token}/setWebhook", [
            'url' => $settings->webhook_url,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        $settings->last_webhook_status = $response->successful() ? 'active' : 'failed';
        $settings->save();

        if (! $response->successful()) {
            Log::warning('Failed to set Telegram webhook', [
                'tenant_id' => $settings->tenant_id,
                'error' => $response->json(),
            ]);
        }
    }

    /**
     * Fetch bot info from Telegram API.
     */
    protected function fetchBotInfo(string $token): ?array
    {
        $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

        if ($response->successful()) {
            $data = $response->json();

            return [
                'username' => $data['result']['username'] ?? '',
                'first_name' => $data['result']['first_name'] ?? '',
            ];
        }

        return null;
    }

    /**
     * Mask a bot token for display.
     */
    protected function maskToken(string $token): string
    {
        if (strlen($token) <= 10) {
            return substr($token, 0, 3).str_repeat('*', strlen($token) - 3);
        }

        return substr($token, 0, 5).str_repeat('*', strlen($token) - 8).substr($token, -3);
    }
}
