<?php

namespace App\Services;

use App\Models\ProviderUser;
use App\Models\Location;
use App\Models\Provider;
use Illuminate\Support\Facades\Auth;

class ProviderUserAuthorityService
{
    /**
     * Resolve authority for a provider user.
     */
    public function resolveAuthority(ProviderUser $providerUser): array
    {
        return [
            'provider_id' => $providerUser->provider_id,
            'user_id' => $providerUser->user_id,
            'role' => $providerUser->role,
            'authority_scope' => $providerUser->authority_scope,
            'permitted_locations' => $providerUser->getPermittedLocations()->map(fn ($loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'address' => $loc->address,
            ]),
            'capabilities' => [
                'view_staff' => $this->canPerformAction($providerUser, 'view_staff'),
                'manage_staff' => $this->canPerformAction($providerUser, 'manage_staff'),
                'view_bookings' => $this->canPerformAction($providerUser, 'view_bookings'),
                'manage_bookings' => $this->canPerformAction($providerUser, 'manage_bookings'),
                'operate_floor' => $this->canPerformAction($providerUser, 'operate_floor'),
                'manage_services' => $this->canPerformAction($providerUser, 'manage_services'),
                'manage_settings' => $this->canPerformAction($providerUser, 'manage_settings'),
                'manage_invitations' => $this->canPerformAction($providerUser, 'manage_invitations'),
            ],
        ];
    }

    /**
     * Check if a user can access a specific location.
     */
    public function canAccessLocation(ProviderUser $providerUser, string $locationId): bool
    {
        // Validate location belongs to the provider
        $location = Location::where('id', $locationId)
            ->where('provider_id', $providerUser->provider_id)
            ->first();

        if (!$location) {
            return false;
        }

        return $providerUser->canAccessLocation($locationId);
    }

    /**
     * Check if a user can perform an action based on role and authority.
     */
    public function canPerformAction(ProviderUser $providerUser, string $action): bool
    {
        $role = $providerUser->role;

        return match ($action) {
            'view_staff', 'manage_staff' => in_array($role, ['owner', 'manager']),
            'view_bookings', 'manage_bookings' => in_array($role, ['owner', 'manager', 'staff', 'receptionist']),
            'operate_floor' => in_array($role, ['owner', 'manager', 'receptionist', 'staff']),
            'manage_services' => in_array($role, ['owner', 'manager']),
            'manage_settings' => in_array($role, ['owner', 'manager']),
            'manage_invitations' => in_array($role, ['owner', 'manager']),
            default => false,
        };
    }

    /**
     * Validate current context (provider + location) for the authenticated user.
     */
    public function validateCurrentContext(string $providerId, ?string $locationId = null): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $providerUser = ProviderUser::where('user_id', $user->id)
            ->where('provider_id', $providerId)
            ->first();

        if (!$providerUser) {
            return false;
        }

        // If no location specified, provider-level context is valid
        if (!$locationId) {
            return true;
        }

        // Validate location access
        return $this->canAccessLocation($providerUser, $locationId);
    }

    /**
     * Validate location scope for the authenticated user.
     * 
     * Enforces:
     * - provider-wide + no location/all → allowed
     * - provider-wide + valid provider location → allowed
     * - location-scoped + no location/all → forbidden
     * - location-scoped + permitted location → allowed
     * - location-scoped + unpermitted location → forbidden
     */
    public function validateLocationScope(string $providerId, ?string $locationId = null): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $providerUser = ProviderUser::where('user_id', $user->id)
            ->where('provider_id', $providerId)
            ->first();

        if (!$providerUser) {
            return false;
        }

        // Provider-wide users can access any scope
        if ($providerUser->isProviderWide()) {
            return true;
        }

        // Location-scoped users must specify a permitted location
        if ($providerUser->isLocationScoped()) {
            // No location specified - forbidden for location-scoped users
            if (!$locationId) {
                return false;
            }
            // Validate the specific location is permitted
            return $this->canAccessLocation($providerUser, $locationId);
        }

        return false;
    }

    /**
     * Get permitted locations for a provider user.
     */
    public function getPermittedLocations(ProviderUser $providerUser)
    {
        return $providerUser->getPermittedLocations();
    }
}
