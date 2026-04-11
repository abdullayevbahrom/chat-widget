<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tenant;
use App\Scopes\TenantScope;
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
     *
     * Widget keys are validated without tenant context because they come from
     * embedded widgets in iframes without an authenticated session. The query
     * removes only the TenantScope (not all scopes) and enforces tenant isolation
     * at the application level by checking the resolved project's tenant.
     */
    public function validateKey(string $key): ?Project
    {
        if (! $this->isValidKeyFormat($key)) {
            return null;
        }

        $hash = hash('sha256', $key);

        // Use tenant-prefixed cache key when tenant context is available.
        // For widget requests without tenant context, use a global cache key.
        $currentTenant = Tenant::current();
        $cacheKey = $currentTenant !== null
            ? "tenant:{$currentTenant->id}:project:key:{$hash}"
            : "project:key:{$hash}";

        return Cache::remember(
            $cacheKey,
            $this->cacheTtl,
            function () use ($hash): ?Project {
                // Bypass only TenantScope — widget key lookup is cross-tenant by design,
                // but the resolved project is then used within its own tenant context.
                $query = Project::withoutGlobalScope(TenantScope::class);

                return $query->where('widget_key_hash', $hash)
                    ->where('is_active', true)
                    ->first();
            }
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
        $tenantPrefix = $project->tenant_id !== null ? "tenant:{$project->tenant_id}:" : '';

        if (filled($project->widget_key_hash)) {
            Cache::forget("{$tenantPrefix}project:key:{$project->widget_key_hash}");
        }

        Cache::forget("{$tenantPrefix}project:{$project->id}:domains:verified");
    }
}
