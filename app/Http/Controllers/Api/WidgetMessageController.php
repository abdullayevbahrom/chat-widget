<?php

namespace App\Http\Controllers\Api;

use App\Events\WidgetMessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\ConversationService;
use App\Services\MessageAttachmentService;
use App\Services\TelegramBotService;
use App\Services\TelegramService;
use App\Services\VisitorTrackingService;
use App\Traits\TelegramMessageHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WidgetMessageController extends Controller
{
    use TelegramMessageHelpers;

    protected const VISITOR_COOKIE_PREFIX = 'widget_visitor_';

    public function __construct(
        protected TelegramBotService $telegramBotService,
        protected VisitorTrackingService $visitorTrackingService,
        protected MessageAttachmentService $messageAttachmentService,
        protected ConversationService $conversationService,
    ) {}

    /**
     * Store a new visitor message.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $this->initializeTenantContext($project);

        try {
            $validated = $request->validate([
                'message' => [
                    'nullable',
                    'string',
                    'max:2000',
                    Rule::requiredIf(fn (): bool => ! $request->hasFile('attachments')),
                ],
                'visitor_name' => ['nullable', 'string', 'max:100'],
                'visitor_email' => ['nullable', 'email', 'max:255'],
                'attachments' => ['sometimes', 'array', 'max:'.MessageAttachmentService::MAX_ATTACHMENTS],
                'attachments.*' => [
                    'file',
                    'max:'.MessageAttachmentService::MAX_FILE_SIZE_KB,
                    'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
            ]);

            // Sanitize visitor_name to prevent XSS
            if (isset($validated['visitor_name'])) {
                $validated['visitor_name'] = $this->sanitizeVisitorName($validated['visitor_name']);
            }

            // Sanitize visitor_email — additional filter_var check beyond validation rule
            if (isset($validated['visitor_email'])) {
                $sanitizedEmail = filter_var(trim($validated['visitor_email']), FILTER_SANITIZE_EMAIL);
                $validated['visitor_email'] = filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL)
                    ? $sanitizedEmail
                    : null;
            }

            Log::info('Handling widget message create request.', [
                'project_id' => $project->id,
                'has_body' => filled($validated['message'] ?? null),
                'attachment_count' => count($request->file('attachments', [])),
            ]);

            $visitor = $this->resolveVisitor($request, $project);
            $conversation = $this->conversationService->openConversation($visitor, $project);

            // Reject messages on closed conversations
            if ($conversation->isClosed() || $conversation->isArchived()) {
                Log::info('Widget message rejected: conversation is not open.', [
                    'project_id' => $project->id,
                    'conversation_public_id' => $conversation->public_id,
                    'status' => $conversation->status,
                ]);

                return response()->json([
                    'error' => 'This conversation is no longer open.',
                    'code' => 'CONVERSATION_CLOSED',
                    'conversation_status' => $conversation->status,
                ], 400);
            }

            $attachments = $this->messageAttachmentService->storeUploadedAttachments(
                $request->file('attachments', []),
                $project,
                $conversation
            );
            $body = $this->sanitizeBody($validated['message'] ?? null);

            $message = $conversation->messages()->create([
                'tenant_id' => $conversation->tenant_id,
                'sender_type' => $visitor->getMorphClass(),
                'sender_id' => $visitor->id,
                'message_type' => $this->resolveMessageType($attachments),
                'body' => $body,
                'attachments' => $attachments !== [] ? $attachments : null,
                'direction' => Message::DIRECTION_INBOUND,
                'metadata' => array_filter([
                    'visitor_name' => $validated['visitor_name'] ?? null,
                    'visitor_email' => $validated['visitor_email'] ?? null,
                    'attachment_count' => count($attachments),
                ], static fn (mixed $value): bool => filled($value)),
            ]);

            // Broadcast the message to real-time listeners (ignore errors to avoid breaking message storage)
            try {
                broadcast(new WidgetMessageSent($conversation, $message))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Failed to broadcast WidgetMessageSent event.', [
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Forward to Telegram via TelegramService for immediate delivery
            $telegramMessageId = app(TelegramService::class)->sendMessage($conversation, $body);

            if ($telegramMessageId) {
                $message->updateQuietly(['telegram_message_id' => $telegramMessageId]);
            }

            $this->notifyTelegram($project, $message, $validated);
            $this->issueVisitorBinding($request, $project, $visitor);

            Log::info('Stored widget visitor message.', [
                'project_id' => $project->id,
                'conversation_public_id' => $conversation->public_id,
                'message_public_id' => $message->public_id,
                'message_type' => $message->message_type,
            ]);

            return response()->json([
                'success' => true,
                'message' => $this->serializeMessage($message->fresh()),
            ]);
        } finally {
            Tenant::clearCurrent();
        }
    }

    /**
     * Get message history for a visitor.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $this->initializeTenantContext($project);

        try {
            $visitor = $this->resolveBoundVisitor($request, $project);

            if ($visitor === null) {
                Log::info('Widget history request rejected because visitor binding is missing or invalid.', [
                    'project_id' => $project->id,
                    'has_cookie' => $request->cookies->has($this->getVisitorCookieName($project)),
                ]);

                return response()->json(['messages' => [], 'next_cursor' => null, 'has_more' => false]);
            }

            $cursor = $request->integer('cursor');
            $perPage = max(1, min($request->integer('per_page', 50), 100));

            // Allow loading a specific conversation by ID, or fall back to the most recent open one
            $specificConversationId = $request->query('conversation_id');
            if ($specificConversationId !== null) {
                $conversation = Conversation::withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->where('visitor_id', $visitor->id)
                    ->find($specificConversationId);
            } else {
                $conversation = $this->conversationService->getOpenConversation($visitor, $project);
            }

            if ($conversation === null) {
                return response()->json(['messages' => [], 'next_cursor' => null, 'has_more' => false]);
            }

            // Use cursor-based pagination from ConversationService
            $result = $this->conversationService->getMessagesPaginated($conversation, $cursor > 0 ? $cursor : null, $perPage);

            Log::info('Returning widget message history with cursor pagination.', [
                'project_id' => $project->id,
                'visitor_public_id' => $visitor->public_id,
                'conversation_public_id' => $conversation->public_id,
                'message_count' => $result['messages']->count(),
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
            ]);

            return response()->json([
                'messages' => $result['messages']->map(fn (Message $message): array => $this->serializeMessage($message))->values(),
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
            ]);
        } finally {
            Tenant::clearCurrent();
        }
    }

    /**
     * Return WebSocket connection config for the widget.
     *
     * SECURITY: Does not expose Reverb app_key directly. Instead, returns
     * connection parameters that the widget SDK uses to establish a
     * server-authenticated WebSocket session.
     */
    public function wsConnect(Request $request): JsonResponse
    {
        // Return WebSocket connection parameters without exposing Reverb secrets
        return response()->json([
            'ws_host' => config('broadcasting.connections.reverb.options.host', parse_url(config('app.url'), PHP_URL_HOST)),
            'ws_port' => request()->secure() ? 443 : (config('broadcasting.connections.reverb.options.port', 6001)),
            'ws_secure' => request()->secure(),
            // The widget SDK should use the bootstrap token or widget key from the
            // original config response to authenticate via the authorizer callback.
            'ws_path' => '/app/'.config('broadcasting.connections.reverb.app_id'),
        ]);
    }

    /**
     * Resolve or create a visitor bound to the current project.
     */
    protected function resolveVisitor(Request $request, Project $project): Visitor
    {
        $visitor = $this->resolveBoundVisitor($request, $project);

        if ($visitor !== null) {
            $visitor->increment('visit_count');
            $visitor->forceFill(
                $this->visitorTrackingService->buildWidgetVisitorRefreshData($request)
            )->saveQuietly();

            Log::info('Resolved bound widget visitor.', [
                'project_id' => $project->id,
                'visitor_id' => $visitor->id,
            ]);

            return $visitor;
        }

        $sessionId = Str::uuid()->toString();
        $visitor = Visitor::create(
            $this->visitorTrackingService->buildWidgetVisitorData($request, $project->tenant_id, $sessionId)
        );

        Log::info('Created new widget visitor binding.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
        ]);

        return $visitor;
    }

    /**
     * Send a notification to the project's Telegram bot via queue job.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function notifyTelegram(Project $project, Message $message, array $validated): void
    {
        $telegramSetting = TelegramBotSetting::where('tenant_id', $project->tenant_id)->first();

        if ($telegramSetting === null || blank($telegramSetting->bot_token) || blank($telegramSetting->chat_id)) {
            Log::info('Skipping Telegram notification because the tenant chat binding is incomplete.', [
                'project_id' => $project->id,
                'tenant_id' => $project->tenant_id,
                'has_setting' => $telegramSetting !== null,
                'has_chat_id' => filled($telegramSetting?->chat_id),
            ]);

            return;
        }

        $conversation = $message->conversation;

        if ($conversation === null) {
            Log::warning('Skipping Telegram notification because conversation is missing.', [
                'project_id' => $project->id,
                'message_id' => $message->id,
            ]);

            return;
        }

        SendTelegramNotificationJob::dispatch(
            $project->tenant_id,
            $project->id,
            $message->id,
            $conversation->id,
            [
                'visitor_name' => $validated['visitor_name'] ?? null,
                'visitor_email' => $validated['visitor_email'] ?? null,
            ]
        );

        Log::info('Queued Telegram notification with inline keyboard.', [
            'project_id' => $project->id,
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    protected function initializeTenantContext(Project $project): void
    {
        if ($project->tenant !== null) {
            Tenant::setCurrent($project->tenant);
        }
    }

    protected function resolveBoundVisitor(Request $request, Project $project): ?Visitor
    {
        $token = $this->getVisitorTokenFromRequest($request);

        if (blank($token)) {
            return null;
        }

        $payload = $this->decodeVisitorToken($token);

        if ($payload === null || ($payload['project_id'] ?? null) !== $project->id) {
            Log::warning('Rejected widget visitor token because payload is invalid.', [
                'project_id' => $project->id,
            ]);

            return null;
        }

        $visitor = Visitor::query()
            ->whereKey($payload['visitor_id'])
            ->where('tenant_id', $project->tenant_id)
            ->where('session_id', $payload['session_id'])
            ->first();

        if ($visitor === null) {
            Log::warning('Rejected widget visitor token because the visitor record could not be found.', [
                'project_id' => $project->id,
                'visitor_id' => $payload['visitor_id'] ?? null,
            ]);
        }

        return $visitor;
    }

    protected function issueVisitorBinding(Request $request, Project $project, Visitor $visitor): void
    {
        $token = $this->buildVisitorToken($project, $visitor);
        $sameSite = $request->isSecure() ? 'none' : 'lax';

        // Determine cookie domain using the same logic as WidgetBootstrapService
        // to ensure consistent domain handling across all widget cookies
        $host = $request->getHost();
        $domain = $this->determineCookieDomain($host);

        // Cookie TTL reduced from 30 days to 7 days to minimize the window
        // for token replay attacks. Visitors automatically get a new token
        // on each message send.
        Cookie::queue(Cookie::make(
            $this->getVisitorCookieName($project),
            $token,
            60 * 24 * 7, // 7 days
            '/',
            $domain, // explicit domain instead of null
            $request->isSecure(),
            true,
            false,
            $sameSite,
        ));

        Log::info('Issued widget visitor binding token.', [
            'project_id' => $project->id,
            'visitor_public_id' => $visitor->public_id,
            'cookie_name' => $this->getVisitorCookieName($project),
            'cookie_domain' => $domain,
        ]);
    }

    protected function getVisitorTokenFromRequest(Request $request): ?string
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        return $project !== null ? $request->cookie($this->getVisitorCookieName($project)) : null;
    }

    protected function getVisitorCookieName(Project $project): string
    {
        return self::VISITOR_COOKIE_PREFIX.$project->id;
    }

    protected function buildVisitorToken(Project $project, Visitor $visitor): string
    {
        return Crypt::encryptString(json_encode([
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'session_id' => $visitor->session_id,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Determine the appropriate cookie domain for the given host.
     *
     * Matches the logic in WidgetBootstrapService to ensure consistent
     * cookie domain handling across all widget endpoints.
     *
     * Returns an empty string for IP addresses and localhost (browser uses
     * default current host). For domain names, returns the exact host
     * without a leading dot to prevent cross-domain cookie leakage.
     */
    protected function determineCookieDomain(string $host): string
    {
        // Don't set domain for localhost or IP addresses
        if ($host === 'localhost' || $host === '127.0.0.1' || filter_var($host, FILTER_VALIDATE_IP)) {
            return ''; // Let browser use default (current host only)
        }

        // For domain names, use the exact host
        // This prevents cookie leakage to other domains
        return $host;
    }

    /**
     * @return array{project_id:int, visitor_id:int, session_id:string}|null
     */
    protected function decodeVisitorToken(string $token): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::warning('Failed to decode widget visitor token.', [
                'exception' => $exception::class,
            ]);

            return null;
        }

        if (
            ! is_array($decoded)
            || ! isset($decoded['project_id'], $decoded['visitor_id'], $decoded['session_id'])
            || ! is_int($decoded['project_id'])
            || ! is_int($decoded['visitor_id'])
            || ! is_string($decoded['session_id'])
        ) {
            return null;
        }

        return $decoded;
    }

    /**
     * WebSocket auth endpoint for widget private channel subscription.
     *
     * Validates the session ID and returns Pusher auth response.
     * Pusher client SDK sends this during private channel subscription.
     */
    public function wsAuth(Request $request): array
    {
        $sessionId = $request->header('X-Session-Id');

        if (blank($sessionId)) {
            throw new AccessDeniedHttpException('Missing session ID');
        }

        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            throw new AccessDeniedHttpException('Invalid or missing widget key');
        }

        $this->initializeTenantContext($project);

        try {
            $visitor = Visitor::withoutGlobalScopes()
                ->where('tenant_id', $project->tenant_id)
                ->where('session_id', $sessionId)
                ->first();

            if (! $visitor) {
                Log::warning('WebSocket auth rejected: invalid session.', [
                    'project_id' => $project->id,
                    'session_id' => $sessionId,
                ]);

                throw new AccessDeniedHttpException('Invalid session');
            }

            Log::info('WebSocket auth successful.', [
                'project_id' => $project->id,
                'visitor_id' => $visitor->id,
            ]);

            // Return empty array — Pusher will handle the auth response format
            return [];
        } finally {
            Tenant::clearCurrent();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->public_id,
            'conversation_id' => $message->conversation?->public_id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'attachments' => array_values(array_map(
                fn (array $attachment): array => $this->messageAttachmentService->serializeForApi($attachment),
                $message->attachments ?? [],
            )),
            'is_read' => $message->is_read,
            'created_at' => $message->created_at->toISOString(),
        ];
    }
}
