<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\TelegramBotSetting;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\MessageAttachmentService;
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
use Illuminate\Validation\Rule;

class WidgetMessageController extends Controller
{
    protected const VISITOR_COOKIE_PREFIX = 'widget_visitor_';

    public function __construct(
        protected TelegramBotService $telegramBotService,
        protected VisitorTrackingService $visitorTrackingService,
        protected MessageAttachmentService $messageAttachmentService,
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
            'message' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => ! $request->hasFile('attachments')),
            ],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'attachments' => ['sometimes', 'array', 'max:'.MessageAttachmentService::MAX_ATTACHMENTS],
            'attachments.*' => [
                'file',
                'max:'.MessageAttachmentService::MAX_FILE_SIZE_KB,
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ]);

        Log::info('Handling widget message create request.', [
            'project_id' => $project->id,
            'has_body' => filled($validated['message'] ?? null),
            'attachment_count' => count($request->file('attachments', [])),
        ]);

        $visitor = $this->resolveVisitor($request, $project);
        $conversation = $this->resolveOpenConversation($project, $visitor);
        $attachments = $this->messageAttachmentService->storeUploadedAttachments(
            $request->file('attachments', []),
            $project,
            $conversation
        );
        $body = $this->normalizeMessageBody($validated['message'] ?? null);

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

        $this->notifyTelegram($project, $message, $validated);
        $this->issueVisitorBinding($request, $project, $visitor);

        Log::info('Stored widget visitor message.', [
            'project_id' => $project->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'message_type' => $message->message_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => $this->serializeMessage($message->fresh()),
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

        $cursor = $request->integer('cursor');

        $query = Message::query()
            ->whereHas('conversation', function ($conversationQuery) use ($project, $visitor): void {
                $conversationQuery
                    ->where('project_id', $project->id)
                    ->where('visitor_id', $visitor->id);
            })
            ->orderBy('id', 'desc')
            ->limit(50);

        if ($cursor > 0) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->get()->reverse()->values();
        $oldestLoadedId = $messages->first()?->id;
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

        Log::info('Returning widget message history.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'message_count' => $messages->count(),
            'next_cursor' => $hasMore ? $oldestLoadedId : null,
        ]);

        return response()->json([
            'messages' => $messages->map(fn (Message $message): array => $this->serializeMessage($message))->values(),
            'next_cursor' => $hasMore ? $oldestLoadedId : null,
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

        $visitorName = $validated['visitor_name'] ?? 'Anonymous';
        $visitorEmail = $validated['visitor_email'] ?? 'Not provided';
        $attachmentLines = collect($message->attachments ?? [])
            ->map(fn (array $attachment): string => sprintf(
                '- %s (%s)',
                $attachment['original_name'] ?? $attachment['name'] ?? 'attachment',
                $attachment['url'] ?? 'stored without URL'
            ))
            ->implode("\n");

        $text = sprintf(
            "New widget message\n\n"
            ."Conversation: #%d\n"
            ."Project: %s\n"
            ."Visitor: %s\n"
            ."Email: %s\n\n"
            ."Message:\n%s",
            $message->conversation_id,
            $project->name,
            $visitorName,
            $visitorEmail,
            $message->body ?? '[Attachment only]'
        );

        if ($attachmentLines !== '') {
            $text .= "\n\nAttachments:\n".$attachmentLines;
        }

        $text .= "\n\nReply to this Telegram message to answer in the widget.";

        try {
            $response = $this->telegramBotService->sendMessage(
                $telegramSetting->bot_token,
                $telegramSetting->chat_id,
                $text
            );

            if (isset($response['result']['message_id'])) {
                $message->update([
                    'telegram_message_id' => $response['result']['message_id'],
                ]);
            }

            Log::info('Delivered widget notification to Telegram.', [
                'project_id' => $project->id,
                'message_id' => $message->id,
                'telegram_message_id' => $response['result']['message_id'] ?? null,
            ]);
        } catch (\Exception $exception) {
            Log::warning('Failed to send Telegram notification for widget message.', [
                'project_id' => $project->id,
                'message_id' => $message->id,
                'exception' => $exception::class,
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

    protected function normalizeMessageBody(mixed $body): ?string
    {
        if (! is_string($body)) {
            return null;
        }

        $normalized = trim($body);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    protected function resolveMessageType(array $attachments): string
    {
        if ($attachments === []) {
            return Message::TYPE_TEXT;
        }

        $hasOnlyImages = collect($attachments)->every(
            fn (array $attachment): bool => str_starts_with((string) ($attachment['mime_type'] ?? ''), 'image/')
        );

        return $hasOnlyImages ? Message::TYPE_IMAGE : Message::TYPE_FILE;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'type' => $message->isInbound() ? 'visitor' : 'admin',
            'message_type' => $message->message_type,
            'direction' => $message->direction,
            'body' => $message->body,
            'attachments' => array_values(array_map(
                fn (array $attachment): array => $this->messageAttachmentService->serializeForApi($attachment),
                $message->attachments ?? [],
            )),
            'is_read' => $message->is_read,
            'sender_type' => $message->sender_type,
            'created_at' => $message->created_at->toISOString(),
        ];
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
