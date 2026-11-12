<?php

namespace App\Policies;

use App\Models\LoyaltyPoint;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoyaltyPolicy
{
    /**
     * Determine whether the user can view any models.
     * Customers can view their own loyalty history.
     * Providers can view loyalty for their business.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LoyaltyPoint $loyaltyPoint): bool
    {
        // Customer can view their own loyalty points
        if ($user->customer && $user->customer->id === $loyaltyPoint->customer_id) {
            return true;
        }

        // Provider can view loyalty points for their business
        if ($user->provider_id === $loyaltyPoint->provider_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * Only internal/provider workflows can award points.
     * Customers cannot create loyalty points directly.
     */
    public function create(User $user): bool
    {
        // Only providers can create (award) points
        return $user->provider_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     * Customers can redeem their own points (which creates a redemption transaction).
     * Providers can manage loyalty for their business.
     */
    public function update(User $user, LoyaltyPoint $loyaltyPoint): bool
    {
        // Customer can only redeem their own points (via redemption transaction)
        if ($user->customer && $user->customer->id === $loyaltyPoint->customer_id) {
            return true;
        }

        // Provider can manage loyalty for their business
        if ($user->provider_id === $loyaltyPoint->provider_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Only providers can delete loyalty records for their business.
     */
    public function delete(User $user, LoyaltyPoint $loyaltyPoint): bool
    {
        return $user->provider_id === $loyaltyPoint->provider_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LoyaltyPoint $loyaltyPoint): bool
    {
        return $user->provider_id === $loyaltyPoint->provider_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LoyaltyPoint $loyaltyPoint): bool
    {
        return $user->provider_id === $loyaltyPoint->provider_id;
    }

    /**
     * Determine whether the user can award points.
     * Only providers can award points for their business.
     */
    public function award(User $user): bool
    {
        return $user->provider_id !== null;
    }

    /**
     * Determine whether the user can redeem points.
     * Customers can redeem their own points.
     */
    public function redeem(User $user, string $customerId): bool
    {
        // Customer can redeem their own points
        if ($user->customer && $user->customer->id === $customerId) {
            return true;
        }

        // Provider can redeem on behalf of customer (if needed)
        return $user->provider_id !== null;
    }
}
