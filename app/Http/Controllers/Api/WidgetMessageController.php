<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\TelegramBotService;
use App\Services\VisitorTrackingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WidgetMessageController extends Controller
{
    protected const VISITOR_COOKIE_PREFIX = 'widget_visitor_';

    public function __construct(
        protected TelegramBotService $telegramBotService,
        protected VisitorTrackingService $visitorTrackingService,
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

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
        ]);

        // Resolve or create visitor
        $visitor = $this->resolveVisitor($request, $project);

        $conversation = $this->resolveOpenConversation($project, $visitor);

        $message = $conversation->messages()->create([
            'tenant_id' => $conversation->tenant_id,
            'sender_type' => $visitor->getMorphClass(),
            'sender_id' => $visitor->id,
            'message_type' => Message::TYPE_TEXT,
            'body' => $validated['message'],
            'direction' => Message::DIRECTION_INBOUND,
            'metadata' => array_filter([
                'visitor_name' => $validated['visitor_name'] ?? null,
                'visitor_email' => $validated['visitor_email'] ?? null,
            ], static fn (mixed $value): bool => filled($value)),
        ]);

        $this->notifyTelegram($project, $message, $visitor, $validated);

        $this->issueVisitorBinding($request, $project, $visitor);

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
        ]);
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

        $visitor = $this->resolveBoundVisitor($request, $project);

        if ($visitor === null) {
            Log::info('Widget history request rejected because visitor binding is missing or invalid.', [
                'project_id' => $project->id,
                'has_cookie' => $request->cookies->has($this->getVisitorCookieName($project)),
            ]);

            return response()->json(['messages' => [], 'next_cursor' => null]);
        }

        $cursor = $request->input('cursor');

        $query = Message::query()
            ->whereHas('conversation', function ($conversationQuery) use ($project, $visitor): void {
                $conversationQuery
                    ->where('project_id', $project->id)
                    ->where('visitor_id', $visitor->id);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(50);

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->get()->reverse()->values();
        $oldestLoadedId = $messages->first()?->id;
        $nextCursor = $oldestLoadedId;

        $hasMore = $oldestLoadedId !== null
            ? Message::query()
                ->whereHas('conversation', function ($conversationQuery) use ($project, $visitor): void {
                    $conversationQuery
                        ->where('project_id', $project->id)
                        ->where('visitor_id', $visitor->id);
                })
                ->where('id', '<', $oldestLoadedId)
                ->exists()
            : false;

        $payload = $messages->map(function (Message $message) {
            return [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'type' => $message->isInbound() ? 'visitor' : 'agent',
                'message_type' => $message->message_type,
                'direction' => $message->direction,
                'body' => $message->body,
                'is_read' => $message->is_read,
                'sender_type' => $message->sender_type,
                'created_at' => $message->created_at->toISOString(),
            ];
        })->values();

        if (! $hasMore) {
            $nextCursor = null;
        }

        return response()->json([
            'messages' => $payload,
            'next_cursor' => $nextCursor,
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
                'session_id' => $visitor->session_id,
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
            'session_id' => $visitor->session_id,
        ]);

        return $visitor;
    }

    protected function resolveOpenConversation(Project $project, Visitor $visitor): Conversation
    {
        Log::info('Resolving open widget conversation.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'tenant_id' => $project->tenant_id,
        ]);

        return DB::transaction(function () use ($project, $visitor): Conversation {
            $conversation = Conversation::withoutGlobalScopes()
                ->where('tenant_id', $project->tenant_id)
                ->where('project_id', $project->id)
                ->where('visitor_id', $visitor->id)
                ->where('open_token', Conversation::OPEN_TOKEN_ACTIVE)
                ->lockForUpdate()
                ->latest('last_message_at')
                ->first();

            if ($conversation !== null) {
                $conversation->forceFill([
                    'last_message_at' => now(),
                ])->saveQuietly();

                Log::info('Reused existing widget conversation for visitor.', [
                    'project_id' => $project->id,
                    'visitor_id' => $visitor->id,
                    'conversation_id' => $conversation->id,
                ]);

                return $conversation;
            }

            try {
                $conversation = Conversation::create([
                    'tenant_id' => $project->tenant_id,
                    'project_id' => $project->id,
                    'visitor_id' => $visitor->id,
                    'status' => Conversation::STATUS_OPEN,
                    'source' => Conversation::SOURCE_WIDGET,
                    'last_message_at' => now(),
                ]);

                Log::info('Created widget conversation for visitor.', [
                    'project_id' => $project->id,
                    'visitor_id' => $visitor->id,
                    'conversation_id' => $conversation->id,
                ]);

                return $conversation;
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                Log::warning('Detected concurrent open conversation creation, reloading existing row.', [
                    'project_id' => $project->id,
                    'visitor_id' => $visitor->id,
                    'sql_state' => $exception->errorInfo[0] ?? null,
                ]);

                return Conversation::withoutGlobalScopes()
                    ->where('tenant_id', $project->tenant_id)
                    ->where('project_id', $project->id)
                    ->where('visitor_id', $visitor->id)
                    ->where('open_token', Conversation::OPEN_TOKEN_ACTIVE)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }, 3);
    }

    /**
     * Send a notification to the project's Telegram bot.
     */
    protected function notifyTelegram(
        Project $project,
        Message $message,
        Visitor $visitor,
        array $validated,
    ): void {
        $telegramSetting = \App\Models\TelegramBotSetting::where('tenant_id', $project->tenant_id)->first();

        if ($telegramSetting === null || blank($telegramSetting->bot_token) || blank($telegramSetting->chat_id)) {
            return;
        }

        $visitorName = $validated['visitor_name'] ?? 'Anonymous';
        $visitorEmail = $validated['visitor_email'] ?? 'Not provided';

        $text = sprintf(
            "💬 *New Message from Widget*\n\n"
            ."📌 *Project:* %s\n"
            ."👤 *Visitor:* %s\n"
            ."📧 *Email:* %s\n\n"
            ."📝 *Message:*\n%s",
            $project->name,
            $visitorName,
            $visitorEmail,
            $message->body
        );

        try {
            $response = $this->telegramBotService->sendMessage(
                $telegramSetting->bot_token,
                $telegramSetting->chat_id,
                $text,
                'Markdown'
            );

            if (isset($response['result']['message_id'])) {
                $message->update([
                    'telegram_message_id' => $response['result']['message_id'],
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send Telegram notification for widget message', [
                'project_id' => $project->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
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

        Cookie::queue(Cookie::make(
            $this->getVisitorCookieName($project),
            $token,
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            $sameSite,
        ));

        Log::info('Issued widget visitor binding token.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'cookie_name' => $this->getVisitorCookieName($project),
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
     * @return array{project_id:int, visitor_id:int, session_id:string}|null
     */
    protected function decodeVisitorToken(string $token): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            Log::warning('Failed to decode widget visitor token.', [
                'error' => $exception->getMessage(),
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

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
