<?php

namespace App\Policies;

use App\Models\TenantDomain;
use App\Models\User;

class TenantDomainPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Super-admin can view all, tenant users can view their own
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isTenantUser();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TenantDomain $tenantDomain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $tenantDomain->tenant_id;
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
    public function update(User $user, TenantDomain $tenantDomain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $tenantDomain->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TenantDomain $tenantDomain): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $tenantDomain->tenant_id;
    }
}
