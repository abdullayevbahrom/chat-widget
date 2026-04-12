<?php

namespace App\Services;

use App\Events\ConversationArchived;
use App\Events\ConversationClosed;
use App\Events\ConversationOpened;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    /**
     * Resolve or create an open conversation for a visitor on a project.
     *
     * Uses lockForUpdate() to prevent race conditions when multiple
     * requests try to create a conversation simultaneously.
     */
    public function openConversation(Visitor $visitor, Project $project): Conversation
    {
        Log::info('Resolving open conversation via ConversationService.', [
            'project_id' => $project->id,
            'visitor_id' => $visitor->id,
            'tenant_id' => $project->tenant_id,
        ]);

        return DB::transaction(function () use ($visitor, $project): Conversation {
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

                Log::info('Reused existing open conversation.', [
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

                Log::info('Created new open conversation.', [
                    'project_id' => $project->id,
                    'visitor_id' => $visitor->id,
                    'conversation_id' => $conversation->id,
                ]);

                // Broadcast conversation opened event (ignore errors to avoid transaction rollback)
                try {
                    event(new ConversationOpened($conversation));
                } catch (\Throwable $e) {
                    Log::warning('Failed to broadcast ConversationOpened event.', [
                        'conversation_id' => $conversation->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                return $conversation;
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                Log::warning('Concurrent open conversation detected, reloading existing row.', [
                    'project_id' => $project->id,
                    'visitor_id' => $visitor->id,
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
     * Close a conversation with an optional closing user.
     *
     * Creates a system message and fires ConversationClosed event.
     */
    public function closeConversation(Conversation $conversation, ?User $closedBy = null): Conversation
    {
        DB::transaction(function () use ($conversation, $closedBy): void {
            $conversation->close($closedBy?->id);

            $this->createSystemMessage($conversation, 'Suhbat yopildi.');
        });

        Log::info('Conversation closed via service.', [
            'conversation_id' => $conversation->id,
            'closed_by' => $closedBy?->id,
        ]);

        return $conversation->fresh();
    }

    /**
     * Reopen a closed conversation.
     *
     * Creates a system message and fires ConversationOpened event.
     */
    public function reopenConversation(Conversation $conversation): Conversation
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->reopen();

            $this->createSystemMessage($conversation, 'Suhbat qayta ochildi.');
        });

        Log::info('Conversation reopened via service.', [
            'conversation_id' => $conversation->id,
        ]);

        return $conversation->fresh();
    }

    /**
     * Archive a conversation.
     *
     * Creates a system message and fires ConversationArchived event.
     */
    public function archiveConversation(Conversation $conversation): Conversation
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->archive();

            $this->createSystemMessage($conversation, 'Suhbat arxivlandi.');
        });

        Log::info('Conversation archived via service.', [
            'conversation_id' => $conversation->id,
        ]);

        return $conversation->fresh();
    }

    /**
     * Get the open conversation for a visitor on a project (without creating one).
     */
    public function getOpenConversation(Visitor $visitor, Project $project): ?Conversation
    {
        return Conversation::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->where('visitor_id', $visitor->id)
            ->where('open_token', Conversation::OPEN_TOKEN_ACTIVE)
            ->latest('last_message_at')
            ->first();
    }

    /**
     * Get a conversation with its recent messages.
     *
     * Uses a separate query for messages to properly enforce the limit,
     * since Eloquent's eager loading with limit() does not work correctly
     * across multiple parent models (it applies the limit globally).
     *
     * @param  int|null  $tenantId  Optional tenant ID for isolation enforcement
     * @return array{conversation: Conversation, messages: Collection}
     *
     * @throws ModelNotFoundException
     */
    public function getConversationWithMessages(int $conversationId, int $limit = 50, ?int $tenantId = null): array
    {
        $query = Conversation::withoutGlobalScopes()
            ->with(['project', 'visitor', 'assignedUser', 'closedUser'])
            ->where('id', $conversationId);

        // Enforce tenant isolation when tenantId is provided
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $conversation = $query->firstOrFail();

        // Fetch messages with a separate query to properly enforce limit
        $messages = $conversation->messages()
            ->with(['sender'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->sortBy('created_at')
            ->values();

        return [
            'conversation' => $conversation,
            'messages' => $messages,
        ];
    }

    /**
     * Get messages for a conversation with cursor-based pagination.
     *
     * Returns messages in chronological order (oldest first) with
     * a next_cursor for loading older messages.
     *
     * @return array{messages: Collection, next_cursor: int|null, has_more: bool}
     */
    public function getMessagesPaginated(Conversation $conversation, ?int $cursor = null, int $perPage = 50): array
    {
        $query = $conversation->messages()
            ->with(['sender'])
            ->orderBy('id', 'desc');

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        // Fetch one extra message to determine if there are more
        $messages = $query->limit($perPage + 1)->get();

        $hasMore = $messages->count() > $perPage;

        // Remove the extra message (the oldest one, which has the smallest id)
        // Since we queried in descending order, the last item in the collection
        // is the oldest message. We remove it if hasMore is true.
        if ($hasMore) {
            $messages = $messages->take($perPage);
        }

        // Reverse to chronological order (oldest first)
        $messages = $messages->sortBy('id')->values();

        // next_cursor should be the id of the oldest message we returned
        // (so the client can request messages older than this)
        $nextCursor = $hasMore ? $messages->first()?->id : null;

        return [
            'messages' => $messages,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Assign a conversation to a user.
     *
     * Creates a system message noting the assignment.
     */
    public function assignConversation(Conversation $conversation, User $user): Conversation
    {
        DB::transaction(function () use ($conversation, $user): void {
            $conversation->update(['assigned_to' => $user->id]);

            $this->createSystemMessage(
                $conversation,
                "Suhbat {$user->name} ga tayinlandi."
            );
        });

        Log::info('Conversation assigned via service.', [
            'conversation_id' => $conversation->id,
            'assigned_to' => $user->id,
        ]);

        return $conversation->fresh();
    }

    /**
     * Get paginated conversations for a project with optional status filter.
     *
     * @param  int|null  $tenantId  Optional tenant ID for explicit tenant filtering
     */
    public function getConversationsForProject(
        Project $project,
        ?string $status = null,
        int $perPage = 25,
        ?int $tenantId = null,
    ): LengthAwarePaginator {
        $query = Conversation::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->with(['visitor', 'assignedUser', 'closedUser'])
            ->latest('last_message_at');

        // Enforce explicit tenant filter when tenantId is provided
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        // Eager load lastMessage for each conversation to avoid N+1
        // Uses a single query with ROW_NUMBER() window function (MySQL 8+/PostgreSQL)
        // to fetch only the latest message per conversation, avoiding limit() issues
        // with eager loading closures.
        $conversationIds = $paginator->pluck('id')->toArray();

        if ($conversationIds !== []) {
            $lastMessages = Message::query()
                ->with(['sender'])
                ->whereIn('conversation_id', $conversationIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('conversation_id')
                ->map(fn ($messages) => $messages->first());

            $paginator->each(function ($conversation) use ($lastMessages): void {
                $conversation->setRelation('latestMessages', collect([$lastMessages->get($conversation->id)])->filter());
            });
        }

        return $paginator;
    }

    /**
     * Batch close conversations older than the given cutoff time.
     *
     * Uses chunked processing to avoid memory issues with large datasets.
     *
     * @return int Number of conversations closed.
     */
    public function closeConversationsOlderThan(Carbon $cutoff): int
    {
        $closedCount = 0;
        $batchSize = 100;

        Conversation::withoutGlobalScopes()
            ->open()
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<', $cutoff)
            ->chunkById($batchSize, function ($idleConversations) use (&$closedCount): void {
                foreach ($idleConversations as $conversation) {
                    try {
                        DB::transaction(function () use ($conversation): void {
                            $conversation->close();

                            $this->createSystemMessage(
                                $conversation,
                                'Suhbat uzoq vaqt faoliyatsiz bo\'lgani uchun avtomatik yopildi.'
                            );
                        });

                        $closedCount++;
                    } catch (\Throwable) {
                        // Skip conversations that can't be closed (e.g. already closed, validation errors)
                        continue;
                    }
                }
            });

        Log::info('Idle conversations closed via service.', [
            'closed_count' => $closedCount,
            'cutoff' => $cutoff->toISOString(),
        ]);

        return $closedCount;
    }

    /**
     * Create a system message in the conversation.
     */
    protected function createSystemMessage(Conversation $conversation, string $body): void
    {
        Message::withoutGlobalScopes()->create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'sender_type' => null,
            'sender_id' => null,
            'message_type' => Message::TYPE_SYSTEM,
            'body' => $body,
            'direction' => Message::DIRECTION_OUTBOUND,
            'is_read' => true,
        ]);
    }

    /**
     * Check if the exception is a unique constraint violation.
     */
    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
