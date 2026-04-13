<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_FILE = 'file';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_EVENT = 'event';

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    /**
     * Temporary cache for sender model (not saved to database).
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    public ?Model $senderCache = null;

    /**
     * Get an attribute from the model, excluding our cache properties.
     */
    public function getAttribute($key): mixed
    {
        if ($key === '_cached_sender') {
            return $this->senderCache;
        }

        return parent::getAttribute($key);
    }

    /**
     * Set a given attribute on the model, excluding our cache properties.
     */
    public function setAttribute($key, $value): mixed
    {
        if ($key === '_cached_sender') {
            return $this->senderCache = $value;
        }

        return parent::setAttribute($key, $value);
    }

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            if (blank($message->public_id)) {
                $message->public_id = (string) Str::uuid();
            }
        });
        static::created(function (Message $message): void {
            // Load conversation relation if not already loaded (uses withoutGlobalScopes to bypass tenant scope)
            $conversation = $message->relationLoaded('conversation')
                ? $message->conversation
                : $message->conversation()->withoutGlobalScopes()->find($message->conversation_id);

            if ($conversation === null) {
                Log::warning('Skipping conversation last_message_at sync because conversation is missing.', [
                    'message_id' => $message->id,
                ]);

                return;
            }

            Log::debug('Syncing conversation last_message_at from message creation.', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'created_at' => optional($message->created_at)?->toISOString(),
            ]);

            $conversation->update([
                'last_message_at' => $message->created_at,
            ]);

            // Invalidate unread count cache for this conversation
            try {
                $conversation->invalidateUnreadCountCache();
            } catch (\Throwable) {
                // Skip cache invalidation if something goes wrong
            }
        });

        static::saving(function (Message $message): void {
            Log::debug('Validating message tenant state before save.', [
                'message_id' => $message->id,
                'tenant_id' => $message->tenant_id,
                'conversation_id' => $message->conversation_id,
                'message_type' => $message->message_type,
                'sender_type' => $message->sender_type,
            ]);

            $conversation = $message->conversation()->withoutGlobalScopes()->first();

            if ($conversation === null) {
                throw new \LogicException('Message conversation must exist before saving.');
            }

            $message->tenant_id = $conversation->tenant_id;

            if (
                in_array($message->message_type, [self::TYPE_SYSTEM, self::TYPE_EVENT], true)
                && $message->sender_type === null
                && $message->sender_id === null
            ) {
                return;
            }

            if ($message->sender_type === null && $message->sender_id === null) {
                throw new \LogicException('Only system or event messages may omit the sender.');
            }

            if (($message->sender_type === null) !== ($message->sender_id === null)) {
                throw new \LogicException('Message sender_type and sender_id must be both null or both present.');
            }

            // Cache sender model on the message to avoid repeated queries
            // when assertSenderIntegrity is called multiple times (e.g. retries)
            $sender = $message->_cached_sender;

            if ($sender === null) {
                $senderClass = Relation::getMorphedModel($message->sender_type) ?? $message->sender_type;
                $allowedSenderClasses = [
                    Visitor::class,
                    User::class,
                    Tenant::class,
                ];

                if (! is_string($senderClass) || ! in_array($senderClass, $allowedSenderClasses, true)) {
                    throw new \LogicException('Message sender_type is not supported.');
                }

                /** @var Model|null $sender */
                $sender = $senderClass::query()->withoutGlobalScopes()->find($message->sender_id);

                if ($sender === null) {
                    throw new \LogicException('Message sender must exist before saving.');
                }

                $message->_cached_sender = $sender;
            }

            $message->assertSenderIntegrity($conversation, $sender);
        });
    }

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'message_type',
        'body',
        'attachments',
        'direction',
        'is_read',
        'read_at',
        'telegram_message_id',
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
            'attachments' => 'array',
            'metadata' => 'array',
            'read_at' => 'datetime',
            'is_read' => 'boolean',
            'message_type' => 'string',
            'direction' => 'string',
        ];
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of the message.
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if this message is inbound.
     */
    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    /**
     * Check if this message is outbound.
     */
    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): void
    {
        Log::info('Marking message as read.', [
            'message_id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'was_read' => $this->is_read,
        ]);

        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Check if the message has attachments.
     */
    public function hasAttachments(): bool
    {
        return filled($this->attachments);
    }

    /**
     * Scope a query to inbound messages.
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_INBOUND);
    }

    /**
     * Scope a query to outbound messages.
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_OUTBOUND);
    }

    /**
     * Scope a query to unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to a specific message type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('message_type', $type);
    }

    /**
     * Assert that the sender belongs to the same tenant as the conversation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $sender  Pre-loaded sender model (cached to avoid repeated queries)
     */
    protected function assertSenderIntegrity(Conversation $conversation, mixed $sender): void
    {
        $allowedSenderClasses = [
            Visitor::class,
            User::class,
            Tenant::class,
        ];

        if (! $sender instanceof Model || ! in_array($sender::class, $allowedSenderClasses, true)) {
            throw new \LogicException('Message sender_type is not supported.');
        }

        if ($sender instanceof Visitor) {
            if ($sender->tenant_id !== $conversation->tenant_id) {
                throw new \LogicException('Message visitor sender must belong to the same tenant.');
            }

            if ($conversation->visitor_id !== $sender->id) {
                throw new \LogicException('Message visitor sender must match the conversation visitor.');
            }

            return;
        }

        if ($sender instanceof User) {
            if ($sender->tenant_id !== $conversation->tenant_id) {
                throw new \LogicException('Message user sender must belong to the conversation tenant.');
            }

            return;
        }

        if ($sender instanceof Tenant && $sender->id !== $conversation->tenant_id) {
            throw new \LogicException('Message tenant sender must match the conversation tenant.');
        }
    }
}
