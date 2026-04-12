<?php

namespace App\Models;

use App\Services\CssSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'domain',
        'description',
        'settings',
        'is_active',
        'telegram_bot_token',
        'telegram_bot_username',
        'telegram_bot_name',
        'telegram_chat_id',
        'telegram_is_active',
        'greeting_message',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'json',
            'is_active' => 'boolean',
            'telegram_is_active' => 'boolean',
            'widget_key_generated_at' => 'datetime',
        ];
    }

    public function setSettingsAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $value = $this->sanitizeSettings($value);
            $this->validateSettingsStructure($value);
        }

        $this->attributes['settings'] = json_encode($value);
    }

    protected function sanitizeSettings(array $settings): array
    {
        if (isset($settings['widget']['custom_css']) && is_string($settings['widget']['custom_css'])) {
            $cssSanitizer = app(CssSanitizer::class);
            $settings['widget']['custom_css'] = $cssSanitizer->sanitize($settings['widget']['custom_css']);
        }

        return $settings;
    }

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

            $allowedWidgetKeys = ['theme', 'position', 'width', 'height', 'primary_color', 'custom_css', 'chat_name'];
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    public function hasActiveConversations(): bool
    {
        return $this->conversations()->open()->exists();
    }

    public function activeConversationsCount(): int
    {
        return $this->conversations()->open()->count();
    }

    /**
     * Get a widget setting with a default value.
     */
    public function getWidgetSetting(string $key, mixed $default = null): mixed
    {
        $widgetSettings = $this->settings['widget'] ?? [];

        return $widgetSettings[$key] ?? $default;
    }

    /**
     * Generate a unique widget key for HMAC authentication.
     */
    public function generateWidgetKey(): string
    {
        $key = bin2hex(random_bytes(32));
        $this->widget_key_hash = hash('sha256', $key);
        $this->widget_key_generated_at = now();
        $this->save();

        return $key;
    }

    /**
     * Regenerate the widget key.
     */
    public function regenerateWidgetKey(): string
    {
        return $this->generateWidgetKey();
    }

    /**
     * Revoke the current widget key.
     */
    public function revokeWidgetKey(): void
    {
        $this->widget_key_hash = null;
        $this->widget_key_generated_at = null;
        $this->save();
    }
}
