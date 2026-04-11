<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Visitor;
use App\Services\ConversationService;
use App\Services\WidgetAntiReplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService,
        protected WidgetAntiReplayService $antiReplayService,
    ) {}

    /**
     * Get the current conversation status for the visitor.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $visitor = $this->resolveBoundVisitor($request, $project);

        if ($visitor === null) {
            return response()->json([
                'conversation' => null,
            ]);
        }

        $conversation = $this->conversationService->getOpenConversation($visitor, $project);

        if ($conversation === null) {
            return response()->json([
                'conversation' => null,
            ]);
        }

        Log::info('Widget conversation status requested.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
        ]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'subject' => $conversation->subject,
                'unread_count' => $conversation->getUnreadCount(),
                'assigned_user' => $conversation->assignedUser?->name,
                'last_message_at' => $conversation->last_message_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Allow the visitor to close their own conversation.
     */
    public function close(Request $request): JsonResponse
    {
        // Require X-Requested-With header to prevent CSRF-like attacks
        $requestedWith = $request->header('X-Requested-With');

        if (blank($requestedWith) || strtolower($requestedWith) !== 'xmlhttprequest') {
            return response()->json([
                'error' => 'X-Requested-With header is required.',
                'code' => 'MISSING_REQUESTED_WITH_HEADER',
            ], 400);
        }

        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null) {
            return response()->json(['error' => 'Invalid or missing widget key.'], 401);
        }

        $visitor = $this->resolveBoundVisitor($request, $project);

        if ($visitor === null) {
            return response()->json(['error' => 'Visitor not identified.'], 401);
        }

        // Validate anti-replay token
        $antiReplayToken = $request->input('anti_replay_token');

        if (blank($antiReplayToken)) {
            return response()->json([
                'error' => 'Anti-replay token is required.',
                'code' => 'MISSING_ANTI_REPLAY_TOKEN',
            ], 400);
        }

        if (! $this->antiReplayService->validateToken($project->id, $visitor->id, $antiReplayToken)) {
            Log::warning('Invalid anti-replay token on close attempt.', [
                'project_id' => $project->id,
                'visitor_id' => $visitor->id,
            ]);

            return response()->json([
                'error' => 'Invalid or expired anti-replay token.',
                'code' => 'INVALID_ANTI_REPLAY_TOKEN',
            ], 403);
        }

        $conversation = $this->conversationService->getOpenConversation($visitor, $project);

        if ($conversation === null) {
            return response()->json([
                'error' => 'No open conversation found.',
                'code' => 'NO_OPEN_CONVERSATION',
            ], 404);
        }

        if (! $conversation->isOpen()) {
            return response()->json([
                'error' => 'Conversation is already closed or archived.',
                'code' => 'CONVERSATION_NOT_OPEN',
            ], 400);
        }

        $this->conversationService->closeConversation($conversation);

        Log::info('Visitor closed their conversation.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'conversation_id' => $conversation->id,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => Conversation::STATUS_CLOSED,
            ],
        ]);
    }

    /**
     * Resolve a visitor bound to the current project via cookie token.
     */
    protected function resolveBoundVisitor(Request $request, Project $project): ?Visitor
    {
        $token = $this->getVisitorTokenFromRequest($request);

        if (blank($token)) {
            return null;
        }

        $payload = $this->decodeVisitorToken($token);

        if ($payload === null || ($payload['project_id'] ?? null) !== $project->id) {
            return null;
        }

        return Visitor::query()
            ->whereKey($payload['visitor_id'])
            ->where('tenant_id', $project->tenant_id)
            ->where('session_id', $payload['session_id'])
            ->first();
    }

    protected function getVisitorTokenFromRequest(Request $request): ?string
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        return $project !== null ? $request->cookie('widget_visitor_'.$project->id) : null;
    }

    /**
     * @return array{project_id:int, visitor_id:int, session_id:string}|null
     */
    protected function decodeVisitorToken(string $token): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(\Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
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
}
