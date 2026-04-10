<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Visitor;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WidgetMessageController extends Controller
{
    public function __construct(
        protected TelegramBotService $telegramBotService,
    ) {}

    /**
     * Store a new visitor message.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
        ]);

        // Resolve or create visitor
        $visitor = $this->resolveVisitor($request, $project);

        // Find or create an open conversation
        $conversation = Conversation::where('project_id', $project->id)
            ->where('visitor_id', $visitor->id)
            ->where('status', 'open')
            ->first();

        if ($conversation === null) {
            $conversation = Conversation::create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'visitor_id' => $visitor->id,
                'status' => 'open',
                'last_message_at' => now(),
            ]);
        } else {
            $conversation->touchLastMessage();
        }

        // Create the message
        $message = Message::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'conversation_id' => $conversation->id,
            'type' => 'visitor',
            'body' => $validated['message'],
        ]);

        // Send notification to Telegram bot if configured
        $this->notifyTelegram($project, $message, $visitor, $validated);

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
        /** @var \App\Models\Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $visitorId = $request->input('visitor_id');

        if ($visitorId === null) {
            return response()->json(['messages' => [], 'next_cursor' => null]);
        }

        $cursor = $request->input('cursor');

        $query = Message::where('project_id', $project->id)
            ->where('visitor_id', $visitorId)
            ->orderBy('created_at', 'desc')
            ->limit(50);

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->get()->reverse()->map(function (Message $message) {
            return [
                'id' => $message->id,
                'type' => $message->type,
                'body' => $message->body,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at->toISOString(),
            ];
        })->values();

        $nextCursor = $messages->isNotEmpty()
            ? $query->reorder('id', 'desc')->limit(1)->value('id')
            : null;

        // If there are more messages beyond the current batch
        $hasMore = Message::where('project_id', $project->id)
            ->where('visitor_id', $visitorId)
            ->where('id', '<', $messages->first()?->id ?? 0)
            ->exists();

        if (! $hasMore) {
            $nextCursor = null;
        }

        return response()->json([
            'messages' => $messages,
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * Resolve or create a visitor for the current session.
     */
    protected function resolveVisitor(Request $request, \App\Models\Project $project): Visitor
    {
        $visitorId = $request->input('visitor_id');

        if ($visitorId !== null) {
            $visitor = Visitor::find($visitorId);
            if ($visitor && $visitor->tenant_id === $project->tenant_id) {
                $visitor->update([
                    'last_visit_at' => now(),
                    'visit_count' => $visitor->visit_count + 1,
                ]);

                return $visitor;
            }
        }

        // Create a new visitor
        $sessionId = $request->input('session_id', Str::uuid()->toString());

        return Visitor::create([
            'tenant_id' => $project->tenant_id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'referer' => $request->headers->get('referer'),
            'current_url' => $request->headers->get('referer'),
            'first_visit_at' => now(),
            'last_visit_at' => now(),
            'visit_count' => 1,
        ]);
    }

    /**
     * Send a notification to the project's Telegram bot.
     */
    protected function notifyTelegram(
        \App\Models\Project $project,
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
}
