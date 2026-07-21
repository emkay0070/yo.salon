<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Customer;
use App\Models\CustomerSalon;
use Illuminate\Support\Facades\DB;

class OfferService
{
    /**
     * Get active offers for a salon
     */
    public function getActiveOffers(string $salonId): array
    {
        $offers = Offer::getActiveForSalon($salonId);

        return $offers->map(function ($offer) {
            return [
                'id' => $offer->id,
                'title' => $offer->title,
                'description' => $offer->description,
                'discount_type' => $offer->discount_type,
                'discount_value' => $offer->discount_value,
                'discount_config' => $offer->discount_config,
                'start_date' => $offer->start_date->toIso8601String(),
                'end_date' => $offer->end_date->toIso8601String(),
                'terms' => $offer->terms,
                'image' => $offer->image,
                'usage_limit' => $offer->usage_limit,
                'usage_count' => $offer->usage_count,
                'remaining_uses' => $offer->usage_limit ? $offer->usage_limit - $offer->usage_count : null,
            ];
        })->toArray();
    }

    /**
     * Get offers eligible for a specific customer
     */
    public function getCustomerEligibleOffers(string $customerId, string $salonId): array
    {
        $customerSalon = CustomerSalon::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->first();

        if (!$customerSalon) {
            return [];
        }

        $offers = Offer::getActiveForSalon($salonId);

        // Filter offers based on customer eligibility
        $eligibleOffers = $offers->filter(function ($offer) use ($customerSalon) {
            // Check if offer has customer-specific eligibility rules
            if ($offer->discount_config && isset($offer->discount_config['min_visits'])) {
                return $customerSalon->visits >= $offer->discount_config['min_visits'];
            }

            return true;
        });

        return $eligibleOffers->map(function ($offer) {
            return [
                'id' => $offer->id,
                'title' => $offer->title,
                'description' => $offer->description,
                'discount_type' => $offer->discount_type,
                'discount_value' => $offer->discount_value,
                'discount_config' => $offer->discount_config,
                'start_date' => $offer->start_date->toIso8601String(),
                'end_date' => $offer->end_date->toIso8601String(),
                'terms' => $offer->terms,
                'image' => $offer->image,
                'usage_limit' => $offer->usage_limit,
                'usage_count' => $offer->usage_count,
                'remaining_uses' => $offer->usage_limit ? $offer->usage_limit - $offer->usage_count : null,
            ];
        })->toArray();
    }

    /**
     * Create a new offer
     */
    public function createOffer(array $data): Offer
    {
        return DB::transaction(function () use ($data) {
            return Offer::create($data);
        });
    }

    /**
     * Update an offer
     */
    public function updateOffer(string $offerId, array $data): Offer
    {
        $offer = Offer::findOrFail($offerId);
        $offer->update($data);
        return $offer->fresh();
    }

    /**
     * Delete an offer
     */
    public function deleteOffer(string $offerId): void
    {
        $offer = Offer::findOrFail($offerId);
        $offer->delete();
    }

    /**
     * Calculate discount amount for an offer
     */
    public function calculateDiscount(Offer $offer, float $originalAmount): float
    {
        return match($offer->discount_type) {
            'percentage' => $originalAmount * ($offer->discount_value / 100),
            'fixed' => min($offer->discount_value, $originalAmount),
            'buy_x_get_y' => $this->calculateBuyXGetYDiscount($offer, $originalAmount),
            default => 0,
        };
    }

    /**
     * Calculate buy X get Y discount
     */
    private function calculateBuyXGetYDiscount(Offer $offer, float $originalAmount): float
    {
        if (!$offer->discount_config) {
            return 0;
        }

        $buyQuantity = $offer->discount_config['buy_quantity'] ?? 1;
        $getQuantity = $offer->discount_config['get_quantity'] ?? 1;
        $discountPercent = $offer->discount_config['discount_percent'] ?? 100;

        // This is a simplified calculation
        // In a real implementation, you'd need to track the actual items/services
        $discountPerSet = ($originalAmount / $buyQuantity) * ($getQuantity * $discountPercent / 100);

        return $discountPerSet;
    }
}
