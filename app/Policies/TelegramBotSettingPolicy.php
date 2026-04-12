<?php

namespace App\Policies;

use App\Models\TelegramBotSetting;
use App\Models\User;

class TelegramBotSettingPolicy
{
    /**
     * Determine whether the user can view any models.
     * Only super admins can view all settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the model.
     * Super admins or the tenant owner can view.
     */
    public function view(User $user, TelegramBotSetting $setting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant->id === $setting->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     * Any authenticated tenant user can create their own setting.
     */
    public function create(User $user): bool
    {
        return $user->tenant->id !== null;
    }

    /**
     * Determine whether the user can update the model.
     * Super admins or the tenant owner can update.
     */
    public function update(User $user, TelegramBotSetting $setting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant->id === $setting->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     * Super admins or the tenant owner can delete.
     */
    public function delete(User $user, TelegramBotSetting $setting): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant->id === $setting->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     * Only super admins can restore.
     */
    public function restore(User $user, TelegramBotSetting $setting): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Only super admins can force delete.
     */
    public function forceDelete(User $user, TelegramBotSetting $setting): bool
    {
        return $user->isSuperAdmin();
    }
}
