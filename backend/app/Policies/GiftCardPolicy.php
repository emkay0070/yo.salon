<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GiftCard;
use Illuminate\Auth\Access\HandlesAuthorization;

class GiftCardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any gift cards
     */
    public function viewAny(User $user): bool
    {
        // Customers can view their own gift cards
        // Providers can view gift cards for their business
        return true;
    }

    /**
     * Determine if the user can view a specific gift card
     */
    public function view(User $user, GiftCard $giftCard): bool
    {
        // Customers can view gift cards they purchased or redeemed
        if ($user->customer && ($user->customer->id === $giftCard->purchased_by || $user->customer->id === $giftCard->redeemed_by)) {
            return true;
        }

        // Providers can view gift cards belonging to their business
        if ($user->provider && $user->provider->id === $giftCard->provider_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create a gift card
     */
    public function create(User $user): bool
    {
        // Only providers can create gift cards
        return $user->provider !== null;
    }

    /**
     * Determine if the user can update a gift card
     */
    public function update(User $user, GiftCard $giftCard): bool
    {
        // Providers can update gift cards for their business
        if ($user->provider && $user->provider->id === $giftCard->provider_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete a gift card
     */
    public function delete(User $user, GiftCard $giftCard): bool
    {
        // Only providers can delete gift cards for their business
        return $user->provider && $user->provider->id === $giftCard->provider_id;
    }

    /**
     * Determine if the user can restore a gift card
     */
    public function restore(User $user, GiftCard $giftCard): bool
    {
        return $user->provider && $user->provider->id === $giftCard->provider_id;
    }

    /**
     * Determine if the user can force delete a gift card
     */
    public function forceDelete(User $user, GiftCard $giftCard): bool
    {
        return $user->provider && $user->provider->id === $giftCard->provider_id;
    }

    /**
     * Determine if the user can purchase a gift card
     */
    public function purchase(User $user): bool
    {
        // Customers can purchase gift cards
        return $user->customer !== null;
    }

    /**
     * Determine if the user can redeem a gift card
     */
    public function redeem(User $user, GiftCard $giftCard): bool
    {
        // Customers can redeem gift cards
        if ($user->customer === null) {
            return false;
        }

        // Gift card must belong to the same provider as the context
        // This is enforced at the service level via provider_id
        return true;
    }

    /**
     * Determine if the user can issue/activate a gift card
     */
    public function issue(User $user, GiftCard $giftCard): bool
    {
        // Only providers can issue gift cards for their business
        return $user->provider && $user->provider->id === $giftCard->provider_id;
    }

    /**
     * Determine if the user can cancel a gift card
     */
    public function cancel(User $user, GiftCard $giftCard): bool
    {
        // Only providers can cancel gift cards for their business
        return $user->provider && $user->provider->id === $giftCard->provider_id;
    }

    /**
     * Determine if the user can adjust a gift card balance
     */
    public function adjust(User $user, GiftCard $giftCard): bool
    {
        // Only providers can adjust gift card balances for their business
        return $user->provider && $user->provider->id === $giftCard->provider_id;
    }

    /**
     * Determine if the user can view transaction history
     */
    public function viewHistory(User $user, GiftCard $giftCard): bool
    {
        return $this->view($user, $giftCard);
    }
}
