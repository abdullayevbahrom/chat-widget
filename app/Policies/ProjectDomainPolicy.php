<?php

namespace App\Policies;

use App\Models\ProjectDomain;
use App\Models\User;

class ProjectDomainPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isTenantUser();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProjectDomain $projectDomain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $projectDomain->project->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isTenantUser();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProjectDomain $projectDomain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $projectDomain->project->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProjectDomain $projectDomain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $projectDomain->project->tenant_id;
    }

    /**
     * Determine whether the user can verify the model.
     */
    public function verify(User $user, ProjectDomain $projectDomain): bool
    {
        return $this->update($user, $projectDomain);
    }
}
