<?php

namespace App\Services;

use App\Models\LoyaltyPoint;
use App\Models\Customer;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Add points to a customer's balance
     */
    public function addPoints(string $customerId, string $salonId, int $points, string $description, array $metadata = []): LoyaltyPoint
    {
        return DB::transaction(function () use ($customerId, $salonId, $points, $description, $metadata) {
            $currentBalance = LoyaltyPoint::getCustomerBalance($customerId, $salonId);
            $newBalance = $currentBalance + $points;

            $loyaltyPoint = LoyaltyPoint::create([
                'customer_id' => $customerId,
                'salon_id' => $salonId,
                'points_earned' => $points,
                'points_redeemed' => 0,
                'balance' => $newBalance,
                'tier' => $this->calculateTier($newBalance),
                'tier_progress' => $this->calculateTierProgress($newBalance),
                'description' => $description,
                'metadata' => $metadata,
            ]);

            return $loyaltyPoint;
        });
    }

    /**
     * Redeem points from a customer's balance
     */
    public function redeemPoints(string $customerId, string $salonId, int $points, string $description, array $metadata = []): LoyaltyPoint
    {
        return DB::transaction(function () use ($customerId, $salonId, $points, $description, $metadata) {
            $currentBalance = LoyaltyPoint::getCustomerBalance($customerId, $salonId);

            if ($currentBalance < $points) {
                throw new \Exception('Insufficient points balance');
            }

            $newBalance = $currentBalance - $points;

            $loyaltyPoint = LoyaltyPoint::create([
                'customer_id' => $customerId,
                'salon_id' => $salonId,
                'points_earned' => 0,
                'points_redeemed' => $points,
                'balance' => $newBalance,
                'tier' => $this->calculateTier($newBalance),
                'tier_progress' => $this->calculateTierProgress($newBalance),
                'redeemed_at' => now(),
                'description' => $description,
                'metadata' => $metadata,
            ]);

            return $loyaltyPoint;
        });
    }

    /**
     * Get customer's loyalty summary
     */
    public function getCustomerSummary(string $customerId, string $salonId): array
    {
        $balance = LoyaltyPoint::getCustomerBalance($customerId, $salonId);
        $tierProgress = LoyaltyPoint::getTierProgress($customerId, $salonId);

        return [
            'balance' => $balance,
            'tier' => $tierProgress['tier'],
            'tier_progress' => $tierProgress['progress'],
            'points_to_next' => $tierProgress['points_to_next'],
            'next_tier' => $tierProgress['next_tier'],
        ];
    }

    /**
     * Get customer's loyalty transaction history
     */
    public function getCustomerHistory(string $customerId, string $salonId, int $limit = 20): array
    {
        $transactions = LoyaltyPoint::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->orderBy('earned_at', 'desc')
            ->limit($limit)
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'points_earned' => $transaction->points_earned,
                'points_redeemed' => $transaction->points_redeemed,
                'balance' => $transaction->balance,
                'tier' => $transaction->tier,
                'description' => $transaction->description,
                'earned_at' => $transaction->earned_at->toIso8601String(),
                'redeemed_at' => $transaction->redeemed_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Award points for a completed booking
     */
    public function awardPointsForBooking(string $bookingId): void
    {
        $booking = Booking::with(['service', 'customer'])->findOrFail($bookingId);

        if ($booking->status !== 'completed') {
            throw new \Exception('Booking must be completed to award points');
        }

        // Award 1 point per $1 spent
        $pointsToAward = (int) $booking->service->price;

        $this->addPoints(
            $booking->customer_id,
            $booking->salon_id,
            $pointsToAward,
            'Points earned for booking: ' . $booking->service->name,
            ['booking_id' => $bookingId]
        );
    }

    /**
     * Calculate tier based on points balance
     */
    private function calculateTier(int $balance): string
    {
        if ($balance >= 1000) return 'platinum';
        if ($balance >= 500) return 'gold';
        if ($balance >= 100) return 'silver';
        return 'bronze';
    }

    /**
     * Calculate tier progress percentage
     */
    private function calculateTierProgress(int $balance): int
    {
        $tierThresholds = [
            'bronze' => 0,
            'silver' => 100,
            'gold' => 500,
            'platinum' => 1000,
        ];

        if ($balance >= 1000) return 100;
        if ($balance >= 500) return 100;
        if ($balance >= 100) return 100;

        // Bronze tier progress (0-100 points)
        return min(100, ($balance / 100) * 100);
    }

    /**
     * Get tier benefits
     */
    public function getTierBenefits(string $tier): array
    {
        return match($tier) {
            'bronze' => [
                'name' => 'Bronze',
                'points_multiplier' => 1,
                'discount_percentage' => 0,
                'priority_booking' => false,
                'exclusive_offers' => false,
            ],
            'silver' => [
                'name' => 'Silver',
                'points_multiplier' => 1.25,
                'discount_percentage' => 5,
                'priority_booking' => false,
                'exclusive_offers' => true,
            ],
            'gold' => [
                'name' => 'Gold',
                'points_multiplier' => 1.5,
                'discount_percentage' => 10,
                'priority_booking' => true,
                'exclusive_offers' => true,
            ],
            'platinum' => [
                'name' => 'Platinum',
                'points_multiplier' => 2,
                'discount_percentage' => 15,
                'priority_booking' => true,
                'exclusive_offers' => true,
            ],
            default => [
                'name' => 'Bronze',
                'points_multiplier' => 1,
                'discount_percentage' => 0,
                'priority_booking' => false,
                'exclusive_offers' => false,
            ],
        };
    }
}
