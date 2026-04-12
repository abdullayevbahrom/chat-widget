<?php

namespace App\Logging;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Enriches log records with contextual information.
 *
 * This Monolog processor adds tenant_id, request_id, user_id,
 * app_version, and environment to every log entry.
 */
class EnrichLogContext
{
    public function __invoke(array $record): array
    {
        // Request ID — use existing or generate new one
        if (! isset($record['context']['request_id'])) {
            $record['context']['request_id'] = (string) Str::uuid();
        }

        // Tenant ID
        if (! isset($record['context']['tenant_id'])) {
            $currentTenant = auth()->check() ? auth()->user()->tenant : null;
            if ($currentTenant !== null) {
                $record['context']['tenant_id'] = $currentTenant->id;
            }
        }

        // Authenticated user ID — skip in CLI/queue context
        if (! isset($record['context']['user_id'])) {
            if (! app()->runningInConsole()) {
                $user = Auth::user();
                if ($user !== null) {
                    $record['context']['user_id'] = $user->id;
                }
            }
        }

        // App version
        if (! isset($record['context']['app_version'])) {
            $record['context']['app_version'] = config('app.version', 'unknown');
        }

        // Environment
        if (! isset($record['context']['environment'])) {
            $record['context']['environment'] = app()->environment();
        }

        return $record;
    }
}
