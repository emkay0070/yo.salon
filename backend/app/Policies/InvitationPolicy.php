<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvitationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any invitations for the provider.
     */
    public function viewAny(User $user): bool
    {
        $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
        if (!$provider) {
            return false;
        }

        $role = $user->providers()
            ->where('providers.id', $provider->id)
            ->first()?->pivot?->role ?? null;

        return in_array($role, ['owner', 'manager']);
    }

    /**
     * Determine if the user can view the invitation.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
        if (!$provider || $invitation->provider_id !== $provider->id) {
            return false;
        }

        $role = $user->providers()
            ->where('providers.id', $provider->id)
            ->first()?->pivot?->role ?? null;

        return in_array($role, ['owner', 'manager']);
    }

    /**
     * Determine if the user can create invitations.
     */
    public function create(User $user): bool
    {
        $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
        if (!$provider) {
            return false;
        }

        $role = $user->providers()
            ->where('providers.id', $provider->id)
            ->first()?->pivot?->role ?? null;

        return in_array($role, ['owner', 'manager']);
    }

    /**
     * Determine if the user can update the invitation (resend, revoke, etc).
     */
    public function update(User $user, Invitation $invitation): bool
    {
        return $this->view($user, $invitation);
    }

    /**
     * Determine if the user can delete the invitation.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->view($user, $invitation);
    }

    /**
     * Scope: Only return invitations belonging to the user's current provider.
     */
    public function scopeForProvider(User $user, $query)
    {
        $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
        if ($provider) {
            return $query->where('provider_id', $provider->id);
        }

        return $query;
    }
}
