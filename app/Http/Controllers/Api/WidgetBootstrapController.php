<?php

namespace App\Http\Controllers\Api;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WidgetBootstrapController extends Controller
{
    /**
     * Bootstrap the widget with project settings, visitor tracking, and conversation state.
     *
     * This endpoint is called by the widget SDK on page load. It:
     * 1. Validates the project via ValidateWidgetDomain middleware
     * 2. Gets or creates a visitor from the session_id (localStorage UUID)
     * 3. Gets the current open conversation or creates one
     * 4. Returns recent message history and WebSocket config
     */
    public function bootstrap(Request $request): JsonResponse
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or unregistered domain.',
            ], 400);
        }

        if (! $project->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'This widget is currently disabled.',
            ], 403);
        }

        // Set tenant context for subsequent queries
        if ($project->tenant !== null) {
            Tenant::setCurrent($project->tenant);
        }

        try {
            // Get or create visitor from session_id (localStorage UUID)
            $sessionId = $request->input('session_id');
            $visitor = null;

            if (filled($sessionId)) {
                $visitor = Visitor::withoutGlobalScopes()->firstOrCreate(
                    [
                        'tenant_id' => $project->tenant_id,
                        'session_id' => $sessionId,
                    ],
                    [
                        'user_agent' => $request->userAgent(),
                        'first_visit_at' => now(),
                        'last_visit_at' => now(),
                        'visit_count' => 1,
                    ]
                );

                // Update last visit if existing
                if (! $visitor->wasRecentlyCreated) {
                    $visitor->update([
                        'last_visit_at' => now(),
                        'visit_count' => $visitor->visit_count + 1,
                        'user_agent' => $request->userAgent(),
                    ]);
                }
            }

            // Get current open conversation or create one
            $conversation = null;
            if ($visitor) {
                $conversation = Conversation::withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->where('visitor_id', $visitor->id)
                    ->where('status', Conversation::STATUS_OPEN)
                    ->latest('created_at')
                    ->first();
            }

            if ($conversation === null) {
                $conversation = Conversation::withoutGlobalScopes()->create([
                    'project_id' => $project->id,
                    'visitor_id' => $visitor?->id,
                    'status' => Conversation::STATUS_OPEN,
                    'source' => Conversation::SOURCE_WIDGET,
                    'open_token' => Conversation::OPEN_TOKEN_ACTIVE,
                ]);
            }

            // Load recent messages (last 20)
            $messages = $conversation->messages()
                ->withoutGlobalScopes()
                ->orderBy('created_at', 'asc')
                ->limit(20)
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->public_id,
                        'body' => $message->body,
                        'sender_type' => $message->sender_type,
                        'sender_id' => $message->sender_id,
                        'direction' => $message->direction,
                        'message_type' => $message->message_type,
                        'created_at' => $message->created_at->toISOString(),
                        'attachments' => $message->attachments,
                    ];
                });

            Log::info('Widget bootstrap completed.', [
                'project_id' => $project->id,
                'conversation_id' => $conversation->public_id,
                'visitor_id' => $visitor?->public_id,
                'message_count' => $messages->count(),
            ]);

            return response()->json([
                'success' => true,
                'project_id' => $project->public_id ?? $project->id,
                'project_name' => $project->name,
                'greeting_message' => $project->greeting_message ?: 'Salom! 👋 Sizga qanday yordam bera olaman?',
                'settings' => [
                    'chat_name' => $project->getWidgetSetting('chat_name', $project->name),
                    'theme' => $project->getWidgetSetting('theme', 'light'),
                    'position' => $project->getWidgetSetting('position', 'bottom-right'),
                    'width' => $project->getWidgetSetting('width', 360),
                    'height' => $project->getWidgetSetting('height', 520),
                    'primary_color' => $project->getWidgetSetting('primary_color', '#6366f1'),
                ],
                'conversation_id' => $conversation->public_id,
                'visitor_id' => $visitor?->public_id,
                'messages' => $messages,
                'websocket' => [
                    'enabled' => config('broadcasting.default') === 'reverb',
                    'app_key' => config('broadcasting.connections.reverb.key'),
                    'app_id' => config('broadcasting.connections.reverb.app_id'),
                    'channel' => 'private-conversation.'.$conversation->public_id,
                    'endpoint' => route('widget.ws.connect', [], false),
                    // Host - strip protocol if present, use request host as fallback
                    'host' => env('REVERB_PUBLIC_HOST', preg_replace('#^https?://#', '', $request->getHost())),
                    'port' => env('REVERB_PUBLIC_PORT', request()->secure() ? 443 : (config('broadcasting.connections.reverb.options.port', 6001))),
                    'use_path' => env('REVERB_USE_PROXY', false) ? '/reverb' : null,
                    'ws_path' => env('REVERB_USE_PROXY', false) ? '/reverb' : '/app/'.config('broadcasting.connections.reverb.app_id'),
                ],
            ]);
        } finally {
            Tenant::clearCurrent();
        }
    }
}
