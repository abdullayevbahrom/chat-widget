<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isTenantUser();
    }

    /**
     * Determine whether the user can view the conversation.
     *
     * Only users from the same tenant can view a conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $conversation->tenant_id;
    }

    /**
     * Determine whether the user can update the conversation (close/reopen/archive).
     */
    public function update(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $conversation->tenant_id;
    }

    /**
     * Determine whether the user can assign the conversation to a user.
     */
    public function assign(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $conversation->tenant_id;
    }

    /**
     * Determine whether the user can delete the conversation.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $conversation->tenant_id;
    }
}
