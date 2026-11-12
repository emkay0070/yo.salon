<?php

namespace App\Policies;

use App\Models\CustomerWorkspaceInvitation;
use App\Models\User;
use App\Models\Customer;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerWorkspaceInvitationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if a specialist can view any invitations for a provider.
     */
    public function viewAny(mixed $user, ?string $providerId = null): bool
    {
        if ($user instanceof Specialist) {
            if (!$providerId) {
                return true;
            }
            return SpecialistAssignment::where('specialist_id', $user->id)
                ->where('provider_id', $providerId)
                ->where('status', 'ACTIVE')
                ->exists();
        }

        if ($user instanceof Customer) {
            return true;
        }

        if ($user instanceof User) {
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if (!$provider) {
                return false;
            }
            $role = $user->providers()
                ->where('providers.id', $provider->id)
                ->first()?->pivot?->role ?? null;

            return in_array($role, ['owner', 'manager']);
        }

        return false;
    }

    /**
     * Determine if a user can view a specific invitation.
     */
    public function view(mixed $user, CustomerWorkspaceInvitation $invitation): bool
    {
        if ($user instanceof Specialist) {
            return $user->id === $invitation->specialist_id;
        }

        if ($user instanceof Customer) {
            return $user->id === $invitation->customer_id;
        }

        if ($user instanceof User) {
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if (!$provider) {
                return false;
            }

            if ($invitation->target_provider_id !== $provider->id) {
                return false;
            }

            $role = $user->providers()
                ->where('providers.id', $provider->id)
                ->first()?->pivot?->role ?? null;

            return in_array($role, ['owner', 'manager']);
        }

        return false;
    }

    /**
     * Determine if a specialist can create an invitation.
     */
    public function create(mixed $user, string $targetProviderId): bool
    {
        if ($user instanceof Specialist) {
            return SpecialistAssignment::where('specialist_id', $user->id)
                ->where('provider_id', $targetProviderId)
                ->where('status', 'active')
                ->exists();
        }

        return false;
    }

    /**
     * Determine if the user can update the invitation (mark as opened, etc).
     */
    public function update(mixed $user, CustomerWorkspaceInvitation $invitation): bool
    {
        if ($user instanceof Specialist) {
            return $user->id === $invitation->specialist_id;
        }

        if ($user instanceof Customer) {
            return $user->id === $invitation->customer_id;
        }

        return false;
    }

    /**
     * Determine if the user can delete/decline the invitation.
     */
    public function delete(mixed $user, CustomerWorkspaceInvitation $invitation): bool
    {
        if ($user instanceof Specialist) {
            return $user->id === $invitation->specialist_id;
        }

        if ($user instanceof Customer) {
            return $user->id === $invitation->customer_id;
        }

        if ($user instanceof User) {
            $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;
            if (!$provider || $invitation->target_provider_id !== $provider->id) {
                return false;
            }

            $role = $user->providers()
                ->where('providers.id', $provider->id)
                ->first()?->pivot?->role ?? null;

            return in_array($role, ['owner']);
        }

        return false;
    }

    /**
     * Determine if the customer can accept the invitation.
     */
    public function accept(Customer $customer, CustomerWorkspaceInvitation $invitation): bool
    {
        return $customer->id === $invitation->customer_id && $invitation->canBeAccepted();
    }

    /**
     * Determine if the customer can decline the invitation.
     */
    public function decline(Customer $customer, CustomerWorkspaceInvitation $invitation): bool
    {
        return $customer->id === $invitation->customer_id && $invitation->canBeAccepted();
    }

    /**
     * Scope: Only return invitations to a specific provider target.
     */
    public function scopeToProvider(mixed $user, $query, string $providerId)
    {
        return $query->where('target_provider_id', $providerId);
    }

    /**
     * Scope: Only return invitations from a specific specialist.
     */
    public function scopeFromSpecialist(mixed $user, $query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    /**
     * Scope: Only return invitations for a specific customer.
     */
    public function scopeForCustomer(mixed $user, $query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
