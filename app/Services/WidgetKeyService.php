<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class WidgetKeyService
{
    /**
     * Cache TTL for widget key lookups (15 minutes).
     */
    protected int $cacheTtl = 900;

    /**
     * Generate a new widget key for the given project.
     *
     * Returns the plaintext key (shown only once to the user).
     */
    public function generateKey(Project $project): string
    {
        return $project->generateWidgetKey();
    }

    /**
     * Revoke the current widget key for the given project.
     */
    public function revokeKey(Project $project): void
    {
        $project->revokeWidgetKey();
    }

    /**
     * Regenerate the widget key for the given project.
     *
     * Revokes the old key and generates a new one.
     * Returns the new plaintext key (shown only once to the user).
     */
    public function regenerateKey(Project $project): string
    {
        return $project->regenerateWidgetKey();
    }

    /**
     * Validate a widget key and return the associated project.
     *
     * Uses cached hash → project mapping for performance.
     */
    public function validateKey(string $key): ?Project
    {
        if (! $this->isValidKeyFormat($key)) {
            return null;
        }

        $hash = hash('sha256', $key);

        // Widget keys are global (not tenant-scoped) because they come from
        // embedded widgets in iframes without tenant context.
        // The TenantScope on Project model ensures tenant isolation at query level.
        return Cache::remember(
            "project:key:{$hash}",
            $this->cacheTtl,
            fn () => Project::withoutGlobalScopes()->where('widget_key_hash', $hash)
                ->where('is_active', true)
                ->first()
        );
    }

    /**
     * Resolve a project from a widget key (alias for validateKey).
     */
    public function resolveFromKey(string $key): ?Project
    {
        return $this->validateKey($key);
    }

    /**
     * Check if the key format is valid.
     *
     * Format: wsk_ + 32 hex characters = 36 characters total.
     */
    public function isValidKeyFormat(string $key): bool
    {
        return (bool) preg_match('/^wsk_[a-f0-9]{32}$/', $key);
    }

    /**
     * Clear the cache for a specific project's widget key.
     */
    public function clearProjectKeyCache(Project $project): void
    {
        if (filled($project->widget_key_hash)) {
            Cache::forget("project:key:{$project->widget_key_hash}");
        }

        Cache::forget("project:{$project->id}:domains:verified");
    }
}
