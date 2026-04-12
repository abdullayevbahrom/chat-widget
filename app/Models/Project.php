<?php

namespace App\Models;

use App\Scopes\TenantScope;
use App\Services\CssSanitizer;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
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
        'domain',
        'widget_key_hash',
        'widget_key_prefix',
        'description',
        'settings',
        'widget_key_generated_at',
        'is_active',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_bot_name',
        'telegram_chat_id',
        'telegram_is_active',
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
            'telegram_is_active' => 'boolean',
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
            $this->validateSettingsStructure($value);
        }

        $this->attributes['settings'] = json_encode($value);
    }

    /**
     * Sanitize widget settings to prevent XSS and injection attacks.
     *
     * Delegates CSS sanitization to the dedicated CssSanitizer service
     * to ensure consistent protection and follow the DRY principle.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected function sanitizeSettings(array $settings): array
    {
        if (isset($settings['widget']['custom_css']) && is_string($settings['widget']['custom_css'])) {
            $cssSanitizer = app(CssSanitizer::class);
            $settings['widget']['custom_css'] = $cssSanitizer->sanitize($settings['widget']['custom_css']);
        }

        return $settings;
    }

    /**
     * Validate the structure and types of project settings.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws \InvalidArgumentException
     */
    protected function validateSettingsStructure(array $settings): void
    {
        $allowedTopLevelKeys = ['widget'];

        foreach (array_keys($settings) as $key) {
            if (! in_array($key, $allowedTopLevelKeys, true)) {
                throw new \InvalidArgumentException(
                    "Invalid settings key: '{$key}'. Allowed keys: ".implode(', ', $allowedTopLevelKeys)
                );
            }
        }

        if (isset($settings['widget'])) {
            if (! is_array($settings['widget'])) {
                throw new \InvalidArgumentException('The "widget" settings key must be an array.');
            }

            $allowedWidgetKeys = ['theme', 'position', 'width', 'height', 'primary_color', 'custom_css'];
            $widget = $settings['widget'];

            foreach (array_keys($widget) as $key) {
                if (! in_array($key, $allowedWidgetKeys, true)) {
                    throw new \InvalidArgumentException(
                        "Invalid widget settings key: '{$key}'. Allowed keys: ".implode(', ', $allowedWidgetKeys)
                    );
                }
            }

            // Validate types
            if (isset($widget['theme']) && ! in_array($widget['theme'], ['light', 'dark', 'auto'], true)) {
                throw new \InvalidArgumentException('Widget "theme" must be one of: light, dark, auto.');
            }

            if (isset($widget['position']) && ! in_array($widget['position'], ['bottom-left', 'bottom-right', 'top-left', 'top-right'], true)) {
                throw new \InvalidArgumentException('Widget "position" must be one of: bottom-left, bottom-right, top-left, top-right.');
            }

            if (isset($widget['width']) && (! is_int($widget['width']) || $widget['width'] < 200 || $widget['width'] > 800)) {
                throw new \InvalidArgumentException('Widget "width" must be an integer between 200 and 800.');
            }

            if (isset($widget['height']) && (! is_int($widget['height']) || $widget['height'] < 200 || $widget['height'] > 1200)) {
                throw new \InvalidArgumentException('Widget "height" must be an integer between 200 and 1200.');
            }

            if (isset($widget['primary_color']) && (! is_string($widget['primary_color']) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $widget['primary_color']))) {
                throw new \InvalidArgumentException('Widget "primary_color" must be a valid hex color (e.g. #3B82F6).');
            }

            if (isset($widget['custom_css']) && ! is_string($widget['custom_css'])) {
                throw new \InvalidArgumentException('Widget "custom_css" must be a string.');
            }
        }
    }

    /**
     * Get the tenant that owns this project.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Resolve route binding for tenant dashboard routes without relying on request tenant context.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $routeKeyName = $field ?? $this->getRouteKeyName();
        $query = static::withoutGlobalScopes()->where($routeKeyName, $value);

        $tenantUser = Auth::guard('tenant_user')->user();

        if ($tenantUser?->tenant_id !== null) {
            $query->where('tenant_id', $tenantUser->tenant_id);
        }

        return $query->firstOrFail();
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
        $tenantPrefix = $this->tenant_id !== null ? "tenant:{$this->tenant_id}:" : '';

        if (filled($this->widget_key_hash)) {
            Cache::forget("{$tenantPrefix}project:key:{$this->widget_key_hash}");
        }
    }
}
