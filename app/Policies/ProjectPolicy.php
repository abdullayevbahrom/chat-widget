<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
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
    public function view(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant->id === $project->tenant_id;
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
    public function update(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant->id === $project->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant->id === $project->tenant_id;
    }

    /**
     * Determine whether the user can regenerate the widget key.
     */
    public function regenerateKey(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    /**
     * Determine whether the user can revoke the widget key.
     */
    public function revokeKey(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
