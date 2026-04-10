<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
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
        'name',
        'slug',
        'widget_key_hash',
        'widget_key_prefix',
        'description',
        'primary_domain',
        'settings',
        'widget_key_generated_at',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'json',
            'is_active' => 'boolean',
            'widget_key_generated_at' => 'datetime',
        ];
    }

    /**
     * Sanitize settings before saving to database.
     */
    public function setSettingsAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $value = $this->sanitizeSettings($value);
        }

        $this->attributes['settings'] = json_encode($value);
    }

    /**
     * Sanitize widget settings to prevent XSS and injection attacks.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function sanitizeSettings(array $settings): array
    {
        if (isset($settings['widget']['custom_css']) && is_string($settings['widget']['custom_css'])) {
            // Strip all HTML tags first
            $css = strip_tags($settings['widget']['custom_css']);

            // Remove dangerous CSS patterns (expression, javascript:, url with javascript, etc.)
            $dangerousPatterns = [
                '/expression\s*\(/i',
                '/javascript\s*:/i',
                '/vbscript\s*:/i',
                '/url\s*\(\s*["\']?\s*javascript\s*:/i',
                '/@import\s/i',
                '/behavior\s*:/i',
                '/-moz-binding\s*:/i',
            ];

            foreach ($dangerousPatterns as $pattern) {
                $css = preg_replace($pattern, '/* blocked */', $css);
            }

            $settings['widget']['custom_css'] = trim($css);
        }

        return $settings;
    }

    /**
     * Get the tenant that owns this project.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the domains for this project.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(ProjectDomain::class);
    }

    /**
     * Get the conversations for this project.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Check if the project has any active (open) conversations.
     */
    public function hasActiveConversations(): bool
    {
        return $this->conversations()->open()->exists();
    }

    /**
     * Get the count of active (open) conversations.
     */
    public function activeConversationsCount(): int
    {
        return $this->conversations()->open()->count();
    }

    /**
     * Get only the active domains for this project.
     */
    public function activeDomains(): HasMany
    {
        return $this->hasMany(ProjectDomain::class)->where('is_active', true);
    }

    /**
     * Get only the verified domains for this project.
     */
    public function verifiedDomains(): HasMany
    {
        return $this->hasMany(ProjectDomain::class)
            ->where('verification_status', 'verified')
            ->where('is_active', true);
    }

    /**
     * Check if the project has a widget key.
     */
    public function hasWidgetKey(): bool
    {
        return filled($this->widget_key_hash);
    }

    /**
     * Generate a new widget key for this project.
     *
     * Returns the plaintext key (shown only once to the user).
     */
    public function generateWidgetKey(): string
    {
        $plaintextKey = 'wsk_'.bin2hex(random_bytes(16));
        $hash = hash('sha256', $plaintextKey);
        $prefix = substr($plaintextKey, 0, 8);

        $this->update([
            'widget_key_hash' => $hash,
            'widget_key_prefix' => $prefix,
            'widget_key_generated_at' => now(),
        ]);

        $this->clearKeyCache();

        return $plaintextKey;
    }

    /**
     * Revoke the current widget key.
     */
    public function revokeWidgetKey(): void
    {
        $this->update([
            'widget_key_hash' => null,
            'widget_key_prefix' => null,
            'widget_key_generated_at' => null,
        ]);

        $this->clearKeyCache();
    }

    /**
     * Regenerate the widget key (revoke old + generate new).
     *
     * Returns the new plaintext key (shown only once to the user).
     */
    public function regenerateWidgetKey(): string
    {
        $this->revokeWidgetKey();

        return $this->generateWidgetKey();
    }

    /**
     * Check if the project has at least one verified domain.
     */
    public function hasVerifiedDomain(): bool
    {
        return $this->verifiedDomains()->exists();
    }

    /**
     * Get the widget settings from the settings JSON.
     */
    public function getWidgetSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, "widget.{$key}", $default);
    }

    /**
     * Clear the widget key cache for this project.
     */
    protected function clearKeyCache(): void
    {
        if (filled($this->widget_key_hash)) {
            Cache::forget("project:key:{$this->widget_key_hash}");
        }

        Cache::forget("project:{$this->id}:domains:verified");
    }

    /**
     * Get cached verified domains for this project.
     *
     * @return array<string>
     */
    public function getVerifiedDomainsCache(): array
    {
        return Cache::remember(
            "project:{$this->id}:domains:verified",
            now()->addMinutes(15),
            fn () => $this->verifiedDomains()->pluck('domain')->toArray()
        );
    }

    /**
     * Clear the verified domains cache.
     */
    public function clearVerifiedDomainsCache(): void
    {
        Cache::forget("project:{$this->id}:domains:verified");
    }
}
