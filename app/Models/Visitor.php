<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Visitor extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * The attributes that aren't mass assignable.
     * All fields are fillable from the VisitorTrackingService (trusted source).
     * The real protection is at the service/middleware level — visitor data
     * never comes from user input directly (Issue #16).
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_authenticated' => 'boolean',
            'first_visit_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'visit_count' => 'integer',
        ];
    }

    /**
     * Get the tenant that this visitor belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user associated with this visitor (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the conversations for this visitor.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the messages sent by this visitor.
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }
}
