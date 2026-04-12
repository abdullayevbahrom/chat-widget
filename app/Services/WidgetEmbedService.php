<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Config;

class WidgetEmbedService
{
    /**
     * Generate HMAC signature for widget embed.
     *
     * Creates a time-limited HMAC-SHA256 signature that binds
     * the domain to the project. No widget key needed.
     */
    public function signEmbed(Project $project): array
    {
        $secret = $this->getEmbedSecret();
        $expiresAt = now()->addYears(10)->timestamp; // Long-lived signature for embed scripts

        $payload = implode('|', [
            $project->id,
            $project->domain,
            $expiresAt,
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        return [
            'project_id' => $project->id,
            'domain' => $project->domain,
            'expires_at' => $expiresAt,
            'signature' => $signature,
        ];
    }

    /**
     * Verify HMAC signature for widget embed request.
     */
    public function verifyEmbed(int $projectId, string $domain, int $expiresAt, string $signature): bool
    {
        // Check expiration
        if ($expiresAt < time()) {
            return false;
        }

        $secret = $this->getEmbedSecret();

        $expectedPayload = implode('|', [
            $projectId,
            $domain,
            $expiresAt,
        ]);

        $expectedSignature = hash_hmac('sha256', $expectedPayload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate the full embed script HTML.
     *
     * Produces a simple script tag that can be copy-pasted into any website.
     * Uses HMAC signature bound to domain only - no widget key needed.
     */
    public function generateEmbedScript(Project $project): string
    {
        $signed = $this->signEmbed($project);
        $appUrl = rtrim(config('app.url'), '/');

        // Build query string with HMAC credentials
        $queryParams = http_build_query([
            'project_id' => $signed['project_id'],
            'domain' => $signed['domain'],
            'expires' => $signed['expires_at'],
            'signature' => $signed['signature'],
        ]);

        $scriptUrl = $appUrl . '/widget.js?' . $queryParams;

        return "<script src=\"{$scriptUrl}\" async defer></script>";
    }

    /**
     * Get the HMAC secret from config or environment.
     */
    protected function getEmbedSecret(): string
    {
        return Config::get('app.widget_embed_secret', Config::get('app.key'));
    }
}
