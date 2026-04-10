<?php

namespace App\Observers;

use App\Models\ProjectDomain;
use Illuminate\Support\Str;

class ProjectDomainObserver
{
    /**
     * Handle the ProjectDomain "created" event.
     */
    public function created(ProjectDomain $projectDomain): void
    {
        // Generate verification token directly on the model
        // to avoid triggering another 'updated' event
        $token = Str::random(32);
        $projectDomain->withoutEvents(function () use ($projectDomain, $token) {
            $projectDomain->update([
                'verification_token' => $token,
                'verification_status' => 'pending',
                'verified_at' => null,
                'verification_error' => null,
            ]);
        });

        // Clear project's verified domains cache
        $projectDomain->project->clearVerifiedDomainsCache();
    }

    /**
     * Handle the ProjectDomain "updated" event.
     */
    public function updated(ProjectDomain $projectDomain): void
    {
        // If domain value changed, reset verification status to pending
        if ($projectDomain->isDirty('domain')) {
            $token = Str::random(32);
            $projectDomain->withoutEvents(function () use ($projectDomain, $token) {
                $projectDomain->update([
                    'verification_status' => 'pending',
                    'verified_at' => null,
                    'verification_error' => null,
                    'verification_token' => $token,
                ]);
            });
        }

        // Clear project's verified domains cache if verification status changed
        if ($projectDomain->isDirty('verification_status') || $projectDomain->isDirty('is_active')) {
            $projectDomain->project->clearVerifiedDomainsCache();
        }
    }

    /**
     * Handle the ProjectDomain "deleted" event.
     */
    public function deleted(ProjectDomain $projectDomain): void
    {
        // Clear project's verified domains cache
        $projectDomain->project->clearVerifiedDomainsCache();
    }
}
