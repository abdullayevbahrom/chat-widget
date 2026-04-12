<?php

namespace App\Http\Controllers;

use App\Models\Project;
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
        $projects = Project::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->get();

        $selectedProjectId = request('project_id');
        $project = null;
        $maskedToken = '';
        $botToken = '';

        if ($selectedProjectId) {
            $project = $projects->firstWhere('id', $selectedProjectId);
            if ($project) {
                $botToken = $project->telegram_bot_token;
                $maskedToken = $botToken ? $this->maskToken($botToken) : '';
            }
        }

        return view('tenant.telegram-bot', compact('projects', 'project', 'maskedToken', 'botToken', 'selectedProjectId'));
    }

    /**
     * Update the Telegram bot settings for a project.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::guard('tenant_user')->user();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'bot_username' => ['nullable', 'string', 'max:100'],
            'bot_name' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Verify project belongs to tenant
        $project = Project::withoutGlobalScopes()
            ->where('id', $validated['project_id'])
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        // Only update bot_token if a new value is provided (not masked)
        if (! empty($validated['bot_token']) && $validated['bot_token'] !== str_repeat('*', strlen($validated['bot_token']))) {
            $project->telegram_bot_token = $validated['bot_token'];

            // Fetch bot info from Telegram API
            $botInfo = $this->fetchBotInfo($validated['bot_token']);
            if ($botInfo) {
                $project->telegram_bot_username = '@'.$botInfo['username'];
                $project->telegram_bot_name = $botInfo['first_name'];
            }
        }

        $project->telegram_chat_id = $validated['chat_id'] ?? $project->telegram_chat_id;
        $project->telegram_is_active = $request->boolean('is_active', false);
        $project->save();

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

        $validated = request()->validate([
            'project_id' => ['required', 'exists:projects,id'],
        ]);

        // Verify project belongs to tenant
        $project = Project::withoutGlobalScopes()
            ->where('id', $validated['project_id'])
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $project || ! $project->telegram_bot_token) {
            return response()->json([
                'success' => false,
                'message' => 'Bot token is not configured.',
            ], 400);
        }

        if (! $project->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'Chat ID is not configured.',
            ], 400);
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$project->telegram_bot_token}/sendMessage", [
            'chat_id' => $project->telegram_chat_id,
            'text' => "✅ Test message from ChatWidget\n\nThis is a test message to verify your Telegram bot integration is working correctly.\n\nProject: {$project->name}\nTime: ".now()->format('Y-m-d H:i:s'),
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
     * Delete the webhook URL - REMOVED.
     */
    public function deleteWebhook(): RedirectResponse
    {
        return redirect()
            ->route('dashboard.telegram')
            ->with('error', 'Webhook management has been removed.');
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
