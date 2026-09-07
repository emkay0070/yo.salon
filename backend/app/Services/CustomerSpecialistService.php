<?php

namespace App\Services;

use App\Models\CustomerSpecialist;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class CustomerSpecialistService
{
    /**
     * Create or update customer-specialist relationship based on a booking
     * This should be called whenever a booking is created or completed
     * Relationships are now provider/workspace-specific
     */
    public function trackRelationshipFromBooking(Booking $booking): CustomerSpecialist
    {
        if (!$booking->specialist_id || !$booking->customer_id || !$booking->provider_id) {
            throw new \InvalidArgumentException('Booking must have specialist_id, customer_id, and provider_id');
        }

        return DB::transaction(function () use ($booking) {
            $relationship = CustomerSpecialist::where('customer_id', $booking->customer_id)
                ->where('specialist_id', $booking->specialist_id)
                ->where('provider_id', $booking->provider_id)
                ->first();

            $servicePrice = $booking->service ? $booking->service->price : 0;
            $relationshipOrigin = $this->determineRelationshipOrigin($booking->provider_id);
            $acquisitionSource = CustomerSpecialist::SOURCE_BOOKING;

            if ($relationship) {
                // Update existing provider-specific relationship
                $relationship->update([
                    'last_booking_id' => $booking->id,
                    'total_bookings' => $relationship->total_bookings + 1,
                    'total_spent' => $relationship->total_spent + $servicePrice,
                    'last_interaction_at' => now(),
                    'last_seen_at' => now(),
                    'relationship_score' => $this->calculateRelationshipScore($relationship->total_bookings + 1, $relationship->total_spent + $servicePrice),
                ]);
            } else {
                // Create new provider-specific relationship
                $relationship = CustomerSpecialist::create([
                    'customer_id' => $booking->customer_id,
                    'specialist_id' => $booking->specialist_id,
                    'provider_id' => $booking->provider_id,
                    'relationship_origin' => $relationshipOrigin,
                    'acquisition_source' => $acquisitionSource,
                    'first_booking_id' => $booking->id,
                    'last_booking_id' => $booking->id,
                    'total_bookings' => 1,
                    'total_spent' => $servicePrice,
                    'last_interaction_at' => now(),
                    'last_seen_at' => now(),
                    'relationship_score' => $this->calculateRelationshipScore(1, $servicePrice),
                ]);
            }

            return $relationship;
        });
    }

    /**
     * Determine relationship origin based on provider type
     */
    protected function determineRelationshipOrigin(string $providerId): string
    {
        $provider = \App\Models\Provider::find($providerId);
        if (!$provider) {
            return CustomerSpecialist::ORIGIN_SALON; // Default fallback
        }

        if ($provider->type === \App\Models\Provider::TYPE_SPECIALIST) {
            return CustomerSpecialist::ORIGIN_INDEPENDENT;
        }

        return CustomerSpecialist::ORIGIN_SALON;
    }

    /**
     * Calculate relationship score based on booking history and spending
     * This score can be used for recommendations and relationship strength
     */
    protected function calculateRelationshipScore(int $totalBookings, float $totalSpent): int
    {
        $score = 0;

        // Base score from number of bookings
        $score += min($totalBookings * 10, 50); // Max 50 points from bookings

        // Bonus score from spending (every $100 = 5 points, max 50 points)
        $score += min(intval($totalSpent / 100) * 5, 50);

        // Recency bonus (handled separately via last_interaction_at)

        return $score;
    }

    /**
     * Toggle follow status for a customer-specialist relationship
     * Requires provider_id to ensure workspace-specific relationship
     */
    public function toggleFollow(string $customerId, string $specialistId, string $providerId): CustomerSpecialist
    {
        $relationship = CustomerSpecialist::firstOrCreate(
            ['customer_id' => $customerId, 'specialist_id' => $specialistId, 'provider_id' => $providerId],
            [
                'total_bookings' => 0,
                'total_spent' => 0,
                'relationship_score' => 0,
                'relationship_origin' => $this->determineRelationshipOrigin($providerId),
                'acquisition_source' => CustomerSpecialist::SOURCE_PROFILE,
            ]
        );

        $relationship->update([
            'is_following' => !$relationship->is_following,
            'last_interaction_at' => now(),
        ]);

        return $relationship->fresh();
    }

    /**
     * Toggle favorite status for a customer-specialist relationship
     * Requires provider_id to ensure workspace-specific relationship
     */
    public function toggleFavorite(string $customerId, string $specialistId, string $providerId): CustomerSpecialist
    {
        $relationship = CustomerSpecialist::firstOrCreate(
            ['customer_id' => $customerId, 'specialist_id' => $specialistId, 'provider_id' => $providerId],
            [
                'total_bookings' => 0,
                'total_spent' => 0,
                'relationship_score' => 0,
                'relationship_origin' => $this->determineRelationshipOrigin($providerId),
                'acquisition_source' => CustomerSpecialist::SOURCE_PROFILE,
            ]
        );

        $relationship->update([
            'is_favorite' => !$relationship->is_favorite,
            'last_interaction_at' => now(),
        ]);

        // Favoriting also follows
        if ($relationship->is_favorite && !$relationship->is_following) {
            $relationship->update(['is_following' => true]);
        }

        return $relationship->fresh();
    }

    /**
     * Set rating from customer to specialist
     * Requires provider_id to ensure workspace-specific relationship
     */
    public function setRating(string $customerId, string $specialistId, string $providerId, float $rating): CustomerSpecialist
    {
        $relationship = CustomerSpecialist::firstOrCreate(
            ['customer_id' => $customerId, 'specialist_id' => $specialistId, 'provider_id' => $providerId],
            [
                'total_bookings' => 0,
                'total_spent' => 0,
                'relationship_score' => 0,
                'relationship_origin' => $this->determineRelationshipOrigin($providerId),
                'acquisition_source' => CustomerSpecialist::SOURCE_BOOKING,
            ]
        );

        $relationship->update([
            'rating_given' => $rating,
            'last_interaction_at' => now(),
        ]);

        return $relationship->fresh();
    }

    /**
     * Get customer's followed specialists
     * Optional provider_id filtering for workspace-specific relationships
     */
    public function getFollowedSpecialists(string $customerId, ?string $providerId = null)
    {
        $query = CustomerSpecialist::where('customer_id', $customerId)
            ->where('is_following', true)
            ->with(['specialist', 'provider']);

        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        return $query->orderBy('last_interaction_at', 'desc')->get();
    }

    /**
     * Get customer's favorite specialists
     * Optional provider_id filtering for workspace-specific relationships
     */
    public function getFavoriteSpecialists(string $customerId, ?string $providerId = null)
    {
        $query = CustomerSpecialist::where('customer_id', $customerId)
            ->where('is_favorite', true)
            ->with(['specialist', 'provider']);

        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        return $query->orderBy('last_interaction_at', 'desc')->get();
    }

    /**
     * Get specialist's followers
     * Optional provider_id filtering for workspace-specific relationships
     */
    public function getSpecialistFollowers(string $specialistId, ?string $providerId = null)
    {
        $query = CustomerSpecialist::where('specialist_id', $specialistId)
            ->where('is_following', true)
            ->with(['customer', 'provider']);

        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        return $query->orderBy('last_interaction_at', 'desc')->get();
    }

    /**
     * Notify followers when specialist moves to a new salon
     * This notifies followers across all workspaces unless provider_id is specified
     */
    public function notifySpecialistMoved(string $specialistId, string $newSalonId, string $newSalonName, ?string $providerId = null): void
    {
        $followers = $this->getSpecialistFollowers($specialistId, $providerId);

        foreach ($followers as $relationship) {
            if ($relationship->notifications_enabled) {
                // TODO: Send notification to customer
                // This would integrate with your notification system
                // For now, we'll just log it
                \Log::info('Specialist moved notification', [
                    'customer_id' => $relationship->customer_id,
                    'specialist_id' => $specialistId,
                    'new_salon_id' => $newSalonId,
                    'new_salon_name' => $newSalonName,
                    'provider_id' => $relationship->provider_id,
                ]);
            }
        }
    }
}
