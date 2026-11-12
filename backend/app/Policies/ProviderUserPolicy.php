<?php

namespace App\Policies;

use App\Models\ProviderUser;
use App\Models\User;
use App\Services\ProviderUserAuthorityService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProviderUserPolicy
{
    use HandlesAuthorization;

    protected ProviderUserAuthorityService $authorityService;

    public function __construct(ProviderUserAuthorityService $authorityService)
    {
        $this->authorityService = $authorityService;
    }

    /**
     * Determine if the user can view staff.
     */
    public function viewStaff(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'view_staff');
    }

    /**
     * Determine if the user can manage staff.
     */
    public function manageStaff(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'manage_staff');
    }

    /**
     * Determine if the user can view bookings.
     */
    public function viewBookings(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'view_bookings');
    }

    /**
     * Determine if the user can manage bookings.
     */
    public function manageBookings(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'manage_bookings');
    }

    /**
     * Determine if the user can manage services.
     */
    public function manageServices(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'manage_services');
    }

    /**
     * Determine if the user can manage settings.
     */
    public function manageSettings(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'manage_settings');
    }

    /**
     * Determine if the user can manage invitations.
     */
    public function manageInvitations(User $user, string $providerId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canPerformAction($providerUser, 'manage_invitations');
    }

    /**
     * Determine if the user can access a specific location.
     */
    public function accessLocation(User $user, string $providerId, string $locationId): bool
    {
        $providerUser = $this->getProviderUser($user, $providerId);
        if (!$providerUser) {
            return false;
        }

        return $this->authorityService->canAccessLocation($providerUser, $locationId);
    }

    /**
     * Helper to get ProviderUser for a user and provider.
     */
    protected function getProviderUser(User $user, string $providerId): ?ProviderUser
    {
        return ProviderUser::where('user_id', $user->id)
            ->where('provider_id', $providerId)
            ->first();
    }
}
