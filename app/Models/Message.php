<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
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
        'conversation_id',
        'type',
        'body',
        'attachments',
        'is_read',
        'telegram_message_id',
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
            'is_read' => 'boolean',
        ];
    }

    /**
     * Get the tenant that this message belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the project that this message belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the visitor that sent this message.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the conversation this message belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Check if this message was sent by a visitor.
     */
    public function isFromVisitor(): bool
    {
        return $this->type === 'visitor';
    }

    /**
     * Check if this message was sent by an agent.
     */
    public function isFromAgent(): bool
    {
        return $this->type === 'agent';
    }

    /**
     * Check if this is a system message.
     */
    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Scope a query to only include visitor messages.
     */
    public function scopeVisitor(Builder $query): Builder
    {
        return $query->where('type', 'visitor');
    }

    /**
     * Scope a query to only include agent messages.
     */
    public function scopeAgent(Builder $query): Builder
    {
        return $query->where('type', 'agent');
    }
}
