<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WidgetEmbedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        protected WidgetEmbedService $embedService,
    ) {}

    /**
     * Display a listing of the tenant's projects.
     */
    public function index(Request $request): View
    {
        $user = $request->user('web') ?? $request->user('tenant_user');

        if ($user->isSuperAdmin()) {
            // Super admin sees all projects
            $projects = Project::latest()->paginate(10);
        } else {
            $tenant = $user->tenant;

            if (! $tenant) {
                abort(403, 'No tenant associated with this account.');
            }

            $projects = Project::where('tenant_id', $tenant->id)->latest()->paginate(10);
        }

        return view('tenant.projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(Request $request): View
    {
        $user = $request->user('web') ?? $request->user('tenant_user');
        $tenant = $user->tenant;

        $project = new Project;
        $project->tenant_id = $tenant->id;
        $project->is_active = true;
        $project->settings = [
            'widget' => [
                'chat_name' => 'Support Chat',
                'theme' => 'light',
                'position' => 'bottom-right',
                'width' => 400,
                'height' => 600,
                'primary_color' => '#6366f1',
                'custom_css' => '',
                'privacy_policy_url' => '',
                'telegram_admins' => [],
            ],
        ];

        return view('tenant.projects.form', compact('project'));
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user('web') ?? $request->user('tenant_user');
        $tenant = $user->tenant;

        if (! $tenant) {
            return redirect()->route('login')->withErrors(['auth' => 'You must be logged in.']);
        }

        $domain = $this->normalizeDomain((string) $request->input('domain'));

        if (! $domain) {
            return back()->withErrors([
                'domain' => 'Domen noto‘g‘ri formatda kiritilgan.',
            ])->withInput();
        }

        $request->merge([
            'domain' => $domain,
        ]);

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:projects,domain', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/'],
            'chat_name' => ['nullable', 'string', 'max:100'],
            'greeting_message' => ['nullable', 'string', 'max:500'],
            'theme' => ['required', 'string', 'in:light,dark,auto'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left,top-right,top-left'],
            'width' => ['required', 'integer', 'min:200', 'max:800'],
            'height' => ['required', 'integer', 'min:200', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'privacy_policy_url' => ['nullable', 'url', 'max:500'],
            'telegram_admins' => ['nullable', 'array', 'max:50'],
            'telegram_admins.*.chat_id' => ['nullable', 'string', 'max:255'],
            'telegram_admins.*.name' => ['nullable', 'string', 'max:255'],
            'telegram_admins.*.telegram_user_id' => ['nullable', 'string', 'max:255'],
            'telegram_admins.*.username' => ['nullable', 'string', 'max:255'],
            'telegram_admins_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $domain = strtolower(trim($validated['domain']));
        $chatName = trim($validated['chat_name'] ?? '');
        $telegramAdmins = $this->resolveTelegramAdmins($request, $validated);
        $primaryTelegramChatId = $telegramAdmins[0]['chat_id'] ?? null;

        $project = new Project;
        $project->tenant_id = $tenant->id;
        $project->domain = $domain;
        $project->name = $domain; // name is same as domain
        $project->slug = $this->generateUniqueSlug($domain, (int) $tenant->id);
        $project->is_active = $request->boolean('is_active', true);
        $project->greeting_message = $validated['greeting_message'] ?? null;
        $project->settings = [
            'widget' => [
                'chat_name' => $chatName ?: $domain,
                'theme' => $validated['theme'],
                'position' => $validated['position'],
                'width' => (int) $validated['width'],
                'height' => (int) $validated['height'],
                'primary_color' => $validated['primary_color'],
                'custom_css' => $validated['custom_css'] ?? '',
                'privacy_policy_url' => $validated['privacy_policy_url'] ?? '',
                'telegram_admins' => $telegramAdmins,
            ],
        ];

        $project->telegram_chat_id = $primaryTelegramChatId;
        $project->telegram_is_active = filled($validated['telegram_bot_token']) && filled($primaryTelegramChatId);
        $project->save();

        // Telegram settings
        if (! empty($validated['telegram_bot_token'])) {
            $project->telegram_bot_token = $validated['telegram_bot_token'];

            // Set webhook and fetch bot info from Telegram API
            $this->configureTelegramWebhook($project, $validated['telegram_bot_token']);
        }
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
        $domain = $this->normalizeDomain((string) $request->input('domain'));

        if (! $domain) {
            return back()->withErrors([
                'domain' => "Domen noto'g'ri formatda kiritilgan.",
            ])->withInput();
        }

        $request->merge([
            'domain' => $domain,
        ]);

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:projects,domain,'.$project->id, 'regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}$/'],
            'chat_name' => ['nullable', 'string', 'max:100'],
            'greeting_message' => ['nullable', 'string', 'max:500'],
            'theme' => ['required', 'string', 'in:light,dark,auto'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left,top-right,top-left'],
            'width' => ['required', 'integer', 'min:200', 'max:800'],
            'height' => ['required', 'integer', 'min:200', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'privacy_policy_url' => ['nullable', 'url', 'max:500'],
            'telegram_admins' => ['nullable', 'array', 'max:50'],
            'telegram_admins.*.chat_id' => ['nullable', 'string', 'max:255'],
            'telegram_admins.*.name' => ['nullable', 'string', 'max:255'],
            'telegram_admins.*.telegram_user_id' => ['nullable', 'string', 'max:255'],
            'telegram_admins.*.username' => ['nullable', 'string', 'max:255'],
            'telegram_admins_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $domain = strtolower(trim($validated['domain']));
        $chatName = trim($validated['chat_name'] ?? '');
        $telegramAdmins = $this->resolveTelegramAdmins($request, $validated);
        $primaryTelegramChatId = $telegramAdmins[0]['chat_id'] ?? null;
        $project->domain = $domain;
        $project->name = $domain;
        if (blank($project->slug)) {
            $project->slug = $this->generateUniqueSlug($domain, (int) $project->tenant_id, $project);
        }
        $project->is_active = $request->boolean('is_active', true);
        $project->greeting_message = $validated['greeting_message'] ?? $project->greeting_message;
        $project->settings = [
            'widget' => [
                'chat_name' => $chatName ?: $domain,
                'theme' => $validated['theme'],
                'position' => $validated['position'],
                'width' => (int) $validated['width'],
                'height' => (int) $validated['height'],
                'primary_color' => $validated['primary_color'],
                'custom_css' => $validated['custom_css'] ?? '',
                'privacy_policy_url' => $validated['privacy_policy_url'] ?? '',
                'telegram_admins' => $telegramAdmins,
            ],
        ];

        // Telegram settings - only update token if a new value is provided (not masked)
        $project->telegram_chat_id = $primaryTelegramChatId;

        if (! empty($validated['telegram_bot_token']) && $validated['telegram_bot_token'] !== str_repeat('*', strlen($validated['telegram_bot_token']))) {
            $project->telegram_bot_token = $validated['telegram_bot_token'];

            // Set webhook and fetch bot info from Telegram API
            $this->configureTelegramWebhook($project, $validated['telegram_bot_token']);
        }

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
            $chatProfile = $this->fetchTelegramChatProfile($token, $chatId);

            return response()->json([
                'success' => true,
                'message' => 'Test message sent successfully!',
                'chat' => $chatProfile,
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
     * Parse Telegram admins text area.
     *
     * Each line format:
     * chat_id|name|telegram_user_id
     *
     * @return array<int, array{chat_id: string, name: string|null, telegram_user_id: string|null, username: string|null}>
     */
    protected function parseTelegramAdmins(string $input): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($input)) ?: [];

        return collect($lines)
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line): ?array {
                $parts = array_map('trim', explode('|', $line));
                $chatId = $parts[0] ?? '';

                if ($chatId === '') {
                    return null;
                }

                return [
                    'chat_id' => $chatId,
                    'name' => ($parts[1] ?? '') !== '' ? $parts[1] : null,
                    'telegram_user_id' => ($parts[2] ?? '') !== '' ? $parts[2] : null,
                    'username' => null,
                ];
            })
            ->filter()
            ->unique('chat_id')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{chat_id: string, name: string|null, telegram_user_id: string|null, username: string|null}>
     */
    protected function resolveTelegramAdmins(Request $request, array $validated): array
    {
        $admins = $request->input('telegram_admins');

        if (is_array($admins) && $admins !== []) {
            return collect($admins)
                ->map(function (mixed $admin): ?array {
                    if (! is_array($admin)) {
                        return null;
                    }

                    $chatId = trim((string) ($admin['chat_id'] ?? ''));

                    if ($chatId === '') {
                        return null;
                    }

                    $name = trim((string) ($admin['name'] ?? ''));
                    $telegramUserId = trim((string) ($admin['telegram_user_id'] ?? ''));
                    $username = trim((string) ($admin['username'] ?? ''));

                    return [
                        'chat_id' => $chatId,
                        'name' => $name !== '' ? $name : null,
                        'telegram_user_id' => $telegramUserId !== '' ? $telegramUserId : null,
                        'username' => $username !== '' ? $username : null,
                    ];
                })
                ->filter()
                ->unique('chat_id')
                ->values()
                ->all();
        }

        return $this->parseTelegramAdmins((string) ($validated['telegram_admins_text'] ?? ''));
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
        $appUrl = rtrim(config('app.url'), '/');
        $webhookUrl = "{$appUrl}/api/projects/{$project->id}/webhook";

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
     * Fetch chat metadata from Telegram API.
     *
     * @return array{name: string|null, telegram_user_id: string|null, username: string|null}|null
     */
    protected function fetchTelegramChatProfile(string $token, string $chatId): ?array
    {
        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/getChat", [
            'chat_id' => $chatId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $chat = $response->json('result');

        if (! is_array($chat)) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $chat['first_name'] ?? null,
            $chat['last_name'] ?? null,
        ])));

        if ($name === '') {
            $name = trim((string) ($chat['title'] ?? ''));
        }

        return [
            'name' => $name !== '' ? $name : null,
            'telegram_user_id' => isset($chat['id']) ? (string) $chat['id'] : null,
            'username' => isset($chat['username']) && $chat['username'] !== '' ? (string) $chat['username'] : null,
        ];
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

    private function normalizeDomain(string $input): ?string
    {
        $value = trim(mb_strtolower($input));

        if ($value === '') {
            return null;
        }

        // Protokol yo'q bo'lsa vaqtincha qo'shamiz
        if (! preg_match('#^[a-z][a-z0-9+\-.]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $host = preg_replace('/^www\./i', '', $host);
        $host = rtrim($host, '.');

        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        return $host;
    }
}
