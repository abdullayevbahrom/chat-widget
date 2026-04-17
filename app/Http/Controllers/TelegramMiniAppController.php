<?php

namespace App\Http\Controllers;

use App\Events\WidgetMessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Services\TelegramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

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

    public function store(Request $request, Project $project): RedirectResponse
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

        try {
            broadcast(new WidgetMessageSent($conversation->fresh(), $message->fresh(), $project->tenant?->name));
        } catch (\Throwable) {
            // Message persisted already.
        }

        app(TelegramService::class)->mirrorAdminReply($message->fresh(), $project->tenant?->name);

        return redirect()->to(URL::signedRoute('telegram.mini-app', [
            'project' => $project->id,
            'conversation' => $conversation->public_id,
            'sent' => 1,
        ]));
    }
}
