<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

class ActivateTenantOnEmailVerified
{
    /**
     * Handle the event.
     *
     * When a tenant user verifies their email, automatically activate
     * their tenant account if it's still in pending state.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user->tenant_id === null || $user->is_super_admin) {
            return;
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            return;
        }

        // Only activate if tenant is currently inactive
        if ($tenant->is_active) {
            return;
        }

        $tenant->update(['is_active' => true]);

        Log::info('Tenant auto-activated after email verification', [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);
    }
}
