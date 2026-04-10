<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use LogicException;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
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
            Log::info('Validating conversation tenant state before save.', [
                'conversation_id' => $conversation->id,
                'tenant_id' => $conversation->tenant_id,
                'project_id' => $conversation->project_id,
                'visitor_id' => $conversation->visitor_id,
                'status' => $conversation->status,
            ]);

            $project = $conversation->project()->withoutGlobalScopes()->first();

            if ($project === null) {
                throw new LogicException('Conversation project must exist before saving.');
            }

            $conversation->tenant_id = $project->tenant_id;
            $conversation->open_token = $conversation->status === self::STATUS_OPEN
                ? self::OPEN_TOKEN_ACTIVE
                : null;

            if ($conversation->visitor_id !== null) {
                $visitor = $conversation->visitor()->withoutGlobalScopes()->first();

                if ($visitor === null) {
                    throw new LogicException('Conversation visitor must exist before saving.');
                }

                if ($visitor->tenant_id !== $conversation->tenant_id) {
                    throw new LogicException('Conversation visitor must belong to the same tenant as the project.');
                }
            }

            if ($conversation->assigned_to !== null) {
                $assignedUser = $conversation->assignedUser()->withoutGlobalScopes()->first();

                if ($assignedUser === null) {
                    throw new LogicException('Conversation assigned user must exist before saving.');
                }

                if ($assignedUser->tenant_id !== null && $assignedUser->tenant_id !== $conversation->tenant_id) {
                    throw new LogicException('Conversation assigned user must belong to the same tenant.');
                }
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'project_id',
        'visitor_id',
        'status',
        'subject',
        'source',
        'telegram_chat_id',
        'assigned_to',
        'last_message_at',
        'open_token',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that this conversation belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the project that this conversation belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the visitor that started this conversation.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the assigned user for the conversation.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get all messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the latest messages in this conversation.
     */
    public function latestMessages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    /**
     * Scope a query to open conversations.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope a query to closed conversations.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Scope a query to a specific project.
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope a query to a specific visitor.
     */
    public function scopeForVisitor(Builder $query, int $visitorId): Builder
    {
        return $query->where('visitor_id', $visitorId);
    }

    /**
     * Check if the conversation is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if the conversation is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Mark the conversation as closed.
     */
    public function close(): void
    {
        Log::info('Closing conversation.', [
            'conversation_id' => $this->id,
            'previous_status' => $this->status,
        ]);

        $this->update(['status' => self::STATUS_CLOSED]);
    }

    /**
     * Mark the conversation as open.
     */
    public function reopen(): void
    {
        Log::info('Reopening conversation.', [
            'conversation_id' => $this->id,
            'previous_status' => $this->status,
        ]);

        $this->update(['status' => self::STATUS_OPEN]);
    }

    /**
     * Get the unread message count for this conversation.
     */
    public function getUnreadCount(): int
    {
        return $this->messages()->where('is_read', false)->count();
    }
}
