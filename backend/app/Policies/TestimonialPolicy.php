<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TestimonialPolicy
{
    /**
     * Determine whether the user can view any models.
     * Public can view approved testimonials with consent.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Testimonial $testimonial): bool
    {
        // Provider can view testimonials for their business
        if ($user->provider_id === $testimonial->provider_id) {
            return true;
        }

        // Customer can view their own testimonials
        if ($user->customer && $user->customer->id === $testimonial->customer_id) {
            return true;
        }

        // Approved testimonials with consent are public
        if ($testimonial->status === 'approved' && $testimonial->consent_given && $testimonial->visible) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * Providers can create testimonials for their business.
     */
    public function create(User $user): bool
    {
        return $user->provider_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     * Providers can moderate testimonials for their business.
     * Customers can revoke consent on their own testimonials.
     */
    public function update(User $user, Testimonial $testimonial): bool
    {
        // Provider can moderate testimonials for their business
        if ($user->provider_id === $testimonial->provider_id) {
            return true;
        }

        // Customer can only revoke consent on their own testimonials
        if ($user->customer && $user->customer->id === $testimonial->customer_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Only providers can delete testimonials for their business.
     */
    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->provider_id === $testimonial->provider_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Testimonial $testimonial): bool
    {
        return $user->provider_id === $testimonial->provider_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Testimonial $testimonial): bool
    {
        return $user->provider_id === $testimonial->provider_id;
    }

    /**
     * Determine whether the user can moderate the testimonial.
     * Only providers can moderate testimonials for their business.
     */
    public function moderate(User $user, Testimonial $testimonial): bool
    {
        return $user->provider_id === $testimonial->provider_id;
    }
}
