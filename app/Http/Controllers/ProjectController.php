<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WidgetEmbedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        protected WidgetEmbedService $embedService,
    ) {}
    /**
     * Display a listing of the tenant's projects.
     */
    public function index(): View
    {
        $projects = Project::latest()->paginate(10);

        return view('tenant.projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): View
    {
        $project = new Project();
        $project->is_active = true;
        $project->settings = ['widget' => [
            'theme' => 'light',
            'position' => 'bottom-right',
            'width' => 400,
            'height' => 600,
            'primary_color' => '#6366f1',
            'custom_css' => '',
        ]];

        return view('tenant.projects.form', compact('project'));
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:projects,domain', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/'],
            'chat_name' => ['nullable', 'string', 'max:100'],
            'theme' => ['required', 'string', 'in:light,dark,auto'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left,top-right,top-left'],
            'width' => ['required', 'integer', 'min:200', 'max:800'],
            'height' => ['required', 'integer', 'min:200', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:100'],
        ]);

        $user = Auth::guard('tenant_user')->user();
        $domain = strtolower(trim($validated['domain']));
        $chatName = trim($validated['chat_name'] ?? '');

        $project = new Project();
        $project->tenant_id = $user->tenant_id;
        $project->domain = $domain;
        $project->name = $domain; // name is same as domain
        $project->slug = $this->generateUniqueSlug($domain, (int) $user->tenant_id);
        $project->is_active = $request->boolean('is_active', true);
        $project->settings = [
            'widget' => [
                'chat_name' => $chatName ?: $domain,
                'theme' => $validated['theme'],
                'position' => $validated['position'],
                'width' => (int) $validated['width'],
                'height' => (int) $validated['height'],
                'primary_color' => $validated['primary_color'],
                'custom_css' => $validated['custom_css'] ?? '',
            ],
        ];

        // Telegram settings
        if (! empty($validated['telegram_bot_token'])) {
            $project->telegram_bot_token = $validated['telegram_bot_token'];

            // Set webhook and fetch bot info from Telegram API
            $this->configureTelegramWebhook($project, $validated['telegram_bot_token']);
        }
        $project->telegram_chat_id = $validated['telegram_chat_id'] ?? null;
        $project->telegram_is_active = filled($validated['telegram_bot_token']) && filled($validated['telegram_chat_id']);

        $project->save();

        // Generate embed script (domain + HMAC only, no widget key)
        $embedScript = $this->embedService->generateEmbedScript($project);

        return redirect()
            ->route('dashboard.projects.edit', $project)
            ->with('success', 'Project created successfully.')
            ->with('embed_script', $embedScript);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project): View
    {
        // Generate embed script for display (domain + HMAC only)
        $embedScript = $this->embedService->generateEmbedScript($project);

        return view('tenant.projects.form', compact('project', 'embedScript'));
    }

    /**
     * Update the specified project in storage.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:projects,domain,'.$project->id, 'regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/'],
            'chat_name' => ['nullable', 'string', 'max:100'],
            'theme' => ['required', 'string', 'in:light,dark,auto'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left,top-right,top-left'],
            'width' => ['required', 'integer', 'min:200', 'max:800'],
            'height' => ['required', 'integer', 'min:200', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:100'],
        ]);

        $domain = strtolower(trim($validated['domain']));
        $chatName = trim($validated['chat_name'] ?? '');
        $project->domain = $domain;
        $project->name = $domain;
        if (blank($project->slug)) {
            $project->slug = $this->generateUniqueSlug($domain, (int) $project->tenant_id, $project);
        }
        $project->is_active = $request->boolean('is_active', true);
        $project->settings = [
            'widget' => [
                'chat_name' => $chatName ?: $domain,
                'theme' => $validated['theme'],
                'position' => $validated['position'],
                'width' => (int) $validated['width'],
                'height' => (int) $validated['height'],
                'primary_color' => $validated['primary_color'],
                'custom_css' => $validated['custom_css'] ?? '',
            ],
        ];

        // Telegram settings - only update token if a new value is provided (not masked)
        if (! empty($validated['telegram_bot_token']) && $validated['telegram_bot_token'] !== str_repeat('*', strlen($validated['telegram_bot_token']))) {
            $project->telegram_bot_token = $validated['telegram_bot_token'];

            // Set webhook and fetch bot info from Telegram API
            $this->configureTelegramWebhook($project, $validated['telegram_bot_token']);
        }
        $project->telegram_chat_id = $validated['telegram_chat_id'] ?? $project->telegram_chat_id;
        $project->telegram_is_active = filled($project->telegram_bot_token) && filled($project->telegram_chat_id);

        $project->save();

        return redirect()
            ->route('dashboard.projects.edit', $project)
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified project from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('dashboard.projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    /**
     * Regenerate the widget key for the specified project.
     */
    public function regenerateKey(Project $project): RedirectResponse
    {
        $plaintextKey = $project->regenerateWidgetKey();

        // Generate fresh embed script (domain + HMAC only)
        $embedScript = $this->embedService->generateEmbedScript($project);

        return redirect()
            ->route('dashboard.projects.edit', $project)
            ->with('success', 'Widget key regenerated successfully.')
            ->with('embed_script', $embedScript);
    }

    /**
     * Send a test message to the configured Telegram chat.
     */
    public function sendTestMessage(Request $request, Project $project): JsonResponse
    {
        // Use token from request if provided, otherwise use project's stored token
        $token = $request->input('telegram_bot_token') ?: $project->telegram_bot_token;
        $chatId = $request->input('telegram_chat_id') ?: $project->telegram_chat_id;

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Bot token is not configured.',
            ], 400);
        }

        if (! $chatId) {
            return response()->json([
                'success' => false,
                'message' => 'Chat ID is not configured.',
            ], 400);
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
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
     * Generate a tenant-scoped unique slug for a project.
     */
    private function generateUniqueSlug(string $name, int $tenantId, ?Project $ignoreProject = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'project';
        $slug = $baseSlug;
        $counter = 1;

        while (
            Project::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->when(
                    $ignoreProject,
                    fn ($query) => $query->whereKeyNot($ignoreProject->getKey())
                )
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Configure Telegram webhook and fetch bot info.
     *
     * Sets the webhook URL via Telegram API and fetches bot metadata
     * (username, first_name) for display purposes.
     */
    protected function configureTelegramWebhook(Project $project, string $token): void
    {
        $tenantSlug = $project->tenant->slug;
        $appUrl = rtrim(config('app.url'), '/');
        $webhookUrl = "{$appUrl}/api/telegram/webhook/{$tenantSlug}";

        // Set webhook
        $webhookResponse = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        if ($webhookResponse->successful()) {
            Log::info('Telegram webhook set successfully.', [
                'project_id' => $project->id,
                'webhook_url' => $webhookUrl,
            ]);
        } else {
            Log::warning('Failed to set Telegram webhook.', [
                'project_id' => $project->id,
                'status' => $webhookResponse->status(),
                'body' => $webhookResponse->body(),
            ]);
        }

        // Fetch bot info
        $botInfo = $this->fetchBotInfo($token);
        if ($botInfo) {
            $project->telegram_bot_username = '@'.$botInfo['username'];
            $project->telegram_bot_name = $botInfo['first_name'];
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
}
