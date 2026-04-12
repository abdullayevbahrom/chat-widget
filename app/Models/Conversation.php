<?php

namespace App\Models;

use App\Events\ConversationArchived;
use App\Events\ConversationClosed;
use App\Events\ConversationOpened;
use App\Scopes\TenantScope;
use App\Services\TenantCacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    public const SOURCE_WIDGET = 'widget';

    public const SOURCE_TELEGRAM = 'telegram';

    public const SOURCE_API = 'api';

    public const OPEN_TOKEN_ACTIVE = 'open';

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (Conversation $conversation): void {
            Log::debug('Validating conversation tenant state before save.', [
                'conversation_id' => $conversation->id,
                'tenant_id' => $conversation->tenant_id,
                'project_id' => $conversation->project_id,
                'visitor_id' => $conversation->visitor_id,
                'status' => $conversation->status,
            ]);

            $project = $conversation->project()->withoutGlobalScopes()->first();

            if ($project === null) {
                throw new \LogicException('Conversation project must exist before saving.');
            }

            $conversation->tenant_id = $project->tenant_id;

            if ($conversation->visitor_id !== null) {
                $visitor = $conversation->visitor()->withoutGlobalScopes()->first();

                if ($visitor === null) {
                    throw new \LogicException('Conversation visitor must exist before saving.');
                }

                if ($visitor->tenant_id !== $conversation->tenant_id) {
                    throw new \LogicException('Conversation visitor must belong to the same tenant as the project.');
                }
            }

            if ($conversation->assigned_to !== null) {
                $assignedUser = $conversation->assignedUser()->withoutGlobalScopes()->first();

                if ($assignedUser === null) {
                    throw new \LogicException('Conversation assigned user must exist before saving.');
                }

                if ($assignedUser->tenant_id !== null && $assignedUser->tenant_id !== $conversation->tenant_id) {
                    throw new \LogicException('Conversation assigned user must belong to the same tenant.');
                }
            }
        });
    }

    protected $fillable = [
        'project_id',
        'visitor_id',
        'status',
        'subject',
        'source',
        'telegram_chat_id',
        'assigned_to',
        'last_message_at',
        'closed_at',
        'closed_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => 'string',
            'source' => 'string',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function latestMessages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeForVisitor(Builder $query, int $visitorId): Builder
    {
        return $query->where('visitor_id', $visitorId);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        if ($this->trashed()) {
            return false;
        }

        return match ([$this->status, $newStatus]) {
            [self::STATUS_OPEN, self::STATUS_CLOSED],
            [self::STATUS_OPEN, self::STATUS_ARCHIVED],
            [self::STATUS_CLOSED, self::STATUS_OPEN],
            [self::STATUS_CLOSED, self::STATUS_ARCHIVED] => true,
            default => false,
        };
    }

    protected function assertCanTransitionTo(string $newStatus): void
    {
        if ($this->trashed()) {
            throw new \LogicException('Cannot change status of a soft-deleted conversation.');
        }

        if (! $this->canTransitionTo($newStatus)) {
            throw new \LogicException(
                "Cannot transition conversation from '{$this->status}' to '{$newStatus}'."
            );
        }
    }

    public function close(?int $closedBy = null): void
    {
        $this->assertCanTransitionTo(self::STATUS_CLOSED);

        Log::info('Closing conversation.', [
            'conversation_id' => $this->id,
            'previous_status' => $this->status,
            'closed_by' => $closedBy,
        ]);

        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);

        $this->invalidateUnreadCountCache();

        event(new ConversationClosed($this));
    }

    /**
     * Mark the conversation as open.
     */
    public function reopen(): void
    {
        $this->assertCanTransitionTo(self::STATUS_OPEN);

        Log::info('Reopening conversation.', [
            'conversation_id' => $this->id,
            'previous_status' => $this->status,
        ]);

        $this->update([
            'status' => self::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        $this->invalidateUnreadCountCache();

        event(new ConversationOpened($this));
    }

    /**
     * Mark the conversation as archived.
     */
    public function archive(): void
    {
        $this->assertCanTransitionTo(self::STATUS_ARCHIVED);

        Log::info('Archiving conversation.', [
            'conversation_id' => $this->id,
            'previous_status' => $this->status,
        ]);

        $this->update([
            'status' => self::STATUS_ARCHIVED,
            'closed_at' => now(),
            'closed_by' => null,
        ]);

        $this->invalidateUnreadCountCache();

        event(new ConversationArchived($this));
    }

    /**
     * Get the unread message count for this conversation.
     *
     * Uses Redis caching with a 30-second TTL to avoid repeated count queries.
     * Cache is invalidated when a new message is added or conversation status changes.
     */
    public function getUnreadCount(): int
    {
        $cacheKey = "conversation:unread:{$this->id}";

        return TenantCacheService::rememberByKey($cacheKey, 30, function (): int {
            return $this->messages()->where('is_read', false)->count();
        });
    }

    /**
     * Invalidate the unread count cache for this conversation.
     */
    public function invalidateUnreadCountCache(): void
    {
        try {
            TenantCacheService::forgetByKey("conversation:unread:{$this->id}");
        } catch (\LogicException) {
            // No tenant context — skip cache invalidation gracefully.
        }
    }
}
