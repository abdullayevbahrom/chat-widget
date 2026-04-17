<?php

namespace App\Http\Controllers;

use App\Events\WidgetMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\TelegramService;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Log;

class TelegramMiniAppController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $conversationPublicId = $request->query('conversation');

        $conversations = Conversation::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->with(['visitor'])
            ->latest('last_message_at')
            ->limit(30)
            ->get();

        $conversation = null;
        $messages = collect();

        if (filled($conversationPublicId)) {
            $conversation = Conversation::withoutGlobalScopes()
                ->where('project_id', $project->id)
                ->where('public_id', $conversationPublicId)
                ->with(['visitor', 'messages.sender'])
                ->firstOrFail();

            $messages = $conversation->messages()->with('sender')->orderBy('created_at')->get();
        }

        $listUrl = URL::signedRoute('telegram.mini-app', ['project' => $project->id]);

        return view('telegram.mini-app', compact('project', 'conversations', 'conversation', 'messages', 'listUrl'));
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = Conversation::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('public_id', $validated['conversation_id'])
            ->firstOrFail();

        if ($conversation->isClosed()) {
            $conversation->reopen();
        }

        $message = Message::withoutGlobalScopes()->create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_type' => $project->tenant->getMorphClass(),
            'sender_id' => $project->tenant_id,
            'message_type' => Message::TYPE_TEXT,
            'body' => trim($validated['body']),
            'direction' => Message::DIRECTION_INBOUND,
            'is_read' => false,
            'metadata' => [
                'source' => 'telegram-mini-app',
            ],
        ]);

        $freshConversation = $conversation->fresh();
        $freshMessage = $message->fresh();

        try {
            broadcast(new WidgetMessageSent($freshConversation, $freshMessage, $project->tenant?->name))->toOthers();
        } catch (\Throwable) {
            // Message persisted already.
        }

        app(TelegramService::class)->mirrorAdminReply($freshMessage, $project->tenant?->name);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $freshMessage->id,
                'body' => $freshMessage->body,
                'created_at' => $freshMessage->created_at->format('Y-m-d H:i'),
                'type' => 'admin',
            ],
        ]);
    }

    public function broadcastAuth(Request $request, Project $project): JsonResponse
    {
        if (!URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired signed URL.');
        }

        $conversationPublicId = (string) $request->input('conversation');
        $channelName = (string) $request->input('channel_name');
        $socketId = $request->input('socket_id');

        Log::debug('Reverb auth request received.', [
            'socket_id' => $socketId,
            'conversation_public_id' => $conversationPublicId,
            'project_id' => $project->id,
            'channel' => $channelName,
            'request_method' => $request->method(),
            'request_headers' => $request->headers->all(),
        ]);

        if ($conversationPublicId === '' || $channelName === '' || !$socketId) {
            abort(403, 'Missing conversation or channel.');
        }

        $conversation = Conversation::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('public_id', $conversationPublicId)
            ->first();

        if (!$conversation) {
            abort(403, 'Conversation not found.');
        }

        $expectedChannel = 'private-conversation.' . $conversation->public_id;

        if ($channelName !== $expectedChannel) {
            abort(403, 'Channel mismatch.');
        }

        Log::info('Reverb auth successful.', [
            'project_id' => $project->id,
            'channel' => $channelName,
        ]);

        $reverbSecret = config('broadcasting.connections.reverb.secret');
        $reverbKey = config('broadcasting.connections.reverb.key');
        $stringToSign = "{$socketId}:{$channelName}";
        $signature = hash_hmac('sha256', $stringToSign, $reverbSecret);

        return response()->json([
            'auth' => "{$reverbKey}:{$signature}",
        ]);
    }
}
