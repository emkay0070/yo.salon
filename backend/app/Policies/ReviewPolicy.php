<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewPolicy
{
    /**
     * Determine whether the user can view any models.
     * Public can view approved reviews via API without authentication.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Review $review): bool
    {
        // Customer can view their own reviews
        if ($user->customer && $user->customer->id === $review->customer_id) {
            return true;
        }

        // Provider can view reviews for their locations
        if ($user->provider) {
            $booking = $review->booking;
            if ($booking && $booking->location) {
                return $booking->location->provider_id === $user->provider->id;
            }
        }

        // Approved reviews are public
        return $review->status === 'approved';
    }

    /**
     * Determine whether the user can create models.
     * Customer can create review for their own completed booking.
     */
    public function create(User $user): bool
    {
        return $user->customer !== null;
    }

    /**
     * Determine whether the user can update the model.
     * Providers can moderate reviews for their locations.
     */
    public function update(User $user, Review $review): bool
    {
        // Providers can moderate reviews for their locations
        if ($user->provider) {
            $booking = $review->booking;
            if ($booking && $booking->location) {
                return $booking->location->provider_id === $user->provider->id;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Customers cannot delete reviews - use moderation instead.
     * Only providers/admins can delete.
     */
    public function delete(User $user, Review $review): bool
    {
        // Providers can delete reviews for their locations
        if ($user->provider) {
            $booking = $review->booking;
            if ($booking && $booking->location) {
                return $booking->location->provider_id === $user->provider->id;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Review $review): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Review $review): bool
    {
        return false;
    }

    /**
     * Determine if user can moderate (change status) the review.
     */
    public function moderate(User $user, Review $review): bool
    {
        return $this->update($user, $review);
    }
}
