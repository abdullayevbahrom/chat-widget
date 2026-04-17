<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\ConversationService;
use App\Services\MessageAttachmentService;
use App\Traits\TelegramMessageHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AdminConversationController extends Controller
{
    use TelegramMessageHelpers;

    public function __construct(
        protected ConversationService $conversationService,
        protected MessageAttachmentService $messageAttachmentService,
    ) {}

    /**
     * List conversations with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('viewAny', Conversation::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:open,closed,archived'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'assigned_to' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? null;
        $projectId = $validated['project_id'] ?? null;
        $assignedTo = $validated['assigned_to'] ?? null;
        $perPage = $validated['per_page'] ?? 25;

        // Determine current tenant context for non-super-admins
        $userTenantId = $user->isSuperAdmin() ? null : $user->tenant->id;

        // If non-super-admin has no tenant, return empty results
        if (! $user->isSuperAdmin() && $userTenantId === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ]);
        }

        // Validate that the requested project_id belongs to the current tenant
        if ($projectId !== null && ! $user->isSuperAdmin()) {
            $projectBelongsToTenant = Project::where('id', $projectId)
                ->where('tenant_id', $userTenantId)
                ->exists();

            if (! $projectBelongsToTenant) {
                return response()->json([
                    'error' => 'Project not found or does not belong to your tenant.',
                    'code' => 'PROJECT_NOT_IN_TENANT',
                ], 403);
            }
        }

        // Validate that the requested assigned_to user belongs to the current tenant
        if ($assignedTo !== null && ! $user->isSuperAdmin()) {
            $assigneeBelongsToTenant = User::where('id', $assignedTo)
                ->where('tenant_id', $userTenantId)
                ->exists();

            if (! $assigneeBelongsToTenant) {
                return response()->json([
                    'error' => 'User not found or does not belong to your tenant.',
                    'code' => 'USER_NOT_IN_TENANT',
                ], 403);
            }
        }

        $query = Conversation::query()
            ->with(['visitor', 'project', 'assignedUser', 'closedUser'])
            ->latest('last_message_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        if ($assignedTo !== null) {
            $query->where('assigned_to', $assignedTo);
        }

        // Super admins see all; tenant users see only their tenant's conversations
        if (! $user->isSuperAdmin()) {
            $query->where('tenant_id', $userTenantId);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Show a single conversation with its messages.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('view', $conversation);

        // Enforce tenant isolation: tenant users can only access their own tenant's conversations
        $tenantId = $user->isSuperAdmin() ? null : $user->tenant->id;

        $data = $this->conversationService->getConversationWithMessages($conversation->id, 100, $tenantId);

        // Serialize messages for response
        $messages = $data['messages']->map(function ($message): array {
            $metadataAgentName = $message->metadata['agent_name'] ?? null;
            $senderName = match (true) {
                $message->sender_type === null => 'System',
                $message->sender instanceof Visitor => $message->sender->name ?? 'Visitor',
                $message->sender instanceof User => $message->sender->name,
                $message->sender instanceof Tenant => $metadataAgentName ?? $message->sender->name ?? 'Tenant',
                default => $message->sender_type,
            };

            return [
                'id' => $message->id,
                'message_type' => $message->message_type,
                'direction' => $message->direction,
                'body' => $message->body,
                'attachments' => array_values(array_map(
                    fn (array $attachment): array => $this->messageAttachmentService->serializeForApi($attachment),
                    $message->attachments ?? [],
                )),
                'is_read' => $message->is_read,
                'sender_name' => $senderName,
                'created_at' => $message->created_at->toISOString(),
            ];
        })->values();

        // Serialize visitor info with only needed fields
        $visitor = $data['conversation']->visitor;
        $visitorData = $visitor !== null ? [
            'id' => $visitor->id,
            'name' => $visitor->name ?? 'Anonim',
            'email' => $visitor->user?->email ?? null,
            'first_visit_at' => $visitor->first_visit_at?->toISOString(),
            'last_visit_at' => $visitor->last_visit_at?->toISOString(),
            'visit_count' => $visitor->visit_count,
        ] : null;

        // Serialize assigned user with only needed fields
        $assignedUser = $data['conversation']->assignedUser;
        $assignedToData = $assignedUser !== null ? [
            'id' => $assignedUser->id,
            'name' => $assignedUser->name,
            'email' => $assignedUser->email,
        ] : null;

        // Serialize closed_by user with only needed fields
        $closedUser = $data['conversation']->closedUser;
        $closedByData = $closedUser !== null ? [
            'id' => $closedUser->id,
            'name' => $closedUser->name,
        ] : null;

        return response()->json([
            'conversation' => [
                'id' => $data['conversation']->id,
                'status' => $data['conversation']->status,
                'subject' => $data['conversation']->subject,
                'source' => $data['conversation']->source,
                'project_id' => $data['conversation']->project_id,
                'visitor' => $visitorData,
                'assigned_to' => $assignedToData,
                'closed_by' => $closedByData,
                'last_message_at' => $data['conversation']->last_message_at?->toISOString(),
                'created_at' => $data['conversation']->created_at->toISOString(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Close a conversation.
     */
    public function close(Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('update', $conversation);

        if (! $conversation->canTransitionTo(Conversation::STATUS_CLOSED)) {
            return response()->json([
                'error' => "Conversation cannot be closed from its current status ({$conversation->status}).",
                'code' => 'INVALID_TRANSITION',
            ], 400);
        }

        $this->conversationService->closeConversation($conversation, $user);

        Log::info('Admin closed conversation.', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'closed_at' => $conversation->closed_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Reopen a closed conversation.
     */
    public function reopen(Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('update', $conversation);

        if (! $conversation->canTransitionTo(Conversation::STATUS_OPEN)) {
            return response()->json([
                'error' => "Conversation cannot be reopened from its current status ({$conversation->status}).",
                'code' => 'INVALID_TRANSITION',
            ], 400);
        }

        $this->conversationService->reopenConversation($conversation);

        Log::info('Admin reopened conversation.', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
        ]);
    }

    /**
     * Archive a conversation.
     */
    public function archive(Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('update', $conversation);

        if (! $conversation->canTransitionTo(Conversation::STATUS_ARCHIVED)) {
            return response()->json([
                'error' => "Conversation cannot be archived from its current status ({$conversation->status}).",
                'code' => 'INVALID_TRANSITION',
            ], 400);
        }

        $this->conversationService->archiveConversation($conversation);

        Log::info('Admin archived conversation.', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
        ]);
    }

    /**
     * Assign a conversation to a user.
     */
    public function assign(Conversation $conversation, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('assign', $conversation);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($validated['user_id']);

        // Ensure assignee belongs to the same tenant (unless super admin)
        if (! $user->isSuperAdmin() && $assignee->tenant_id !== $user->tenant->id) {
            return response()->json([
                'error' => 'Cannot assign to a user from a different tenant.',
                'code' => 'CROSS_TENANT_ASSIGNMENT',
            ], 403);
        }

        // Ensure assignee is a valid tenant agent (not a super admin, has verified email)
        if ($assignee->isSuperAdmin()) {
            return response()->json([
                'error' => 'Cannot assign conversation to a super admin.',
                'code' => 'INVALID_ASSIGNEE_ROLE',
            ], 400);
        }

        if ($assignee->email_verified_at === null) {
            return response()->json([
                'error' => 'Cannot assign to an unverified user.',
                'code' => 'INVALID_ASSIGNEE_STATUS',
            ], 400);
        }

        $this->conversationService->assignConversation($conversation, $assignee);

        Log::info('Admin assigned conversation.', [
            'conversation_id' => $conversation->id,
            'assigned_to' => $assignee->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'assigned_to' => $assignee->id,
                'assigned_user' => $assignee->name,
            ],
        ]);
    }

    /**
     * Get the count of conversations with unread messages.
     */
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        Gate::authorize('viewAny', Conversation::class);

        $query = Conversation::query()
            ->whereHas('messages', function ($query): void {
                $query->where('is_read', false);
            });

        // Enforce tenant isolation: non-super-admins can only see their own tenant
        if (! $user->isSuperAdmin()) {
            if ($user->tenant->id !== null) {
                $query->where('tenant_id', $user->tenant->id);
            } else {
                // User has no tenant — return 0 to prevent data leakage
                return response()->json(['unread_count' => 0]);
            }
        }

        $count = $query->distinct()->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }
}
