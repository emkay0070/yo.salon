<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * WalletController - Loyalty and Promo Code Management
 * 
 * This controller now only handles loyalty points and promo codes.
 * Wallet balance functionality has been removed as the platform
 * no longer holds customer funds - payments go directly to providers.
 */
class WalletController extends Controller
{
    /**
     * Get loyalty points summary
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        $providerId = $request->attributes->get('provider_id');

        try {
            $balance = \App\Models\LoyaltyPoint::getCustomerBalance($customerId, $providerId);
            $tier = \App\Models\LoyaltyPoint::getCustomerTier($customerId, $providerId);

            return response()->json([
                'loyalty_points' => $balance,
                'tier' => $tier,
                'currency' => 'UGX',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get loyalty summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get loyalty points history
     */
    public function transactions(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        $salonId = $request->attributes->get('salon_id');
        $limit = $request->query('limit', 20);

        try {
            $points = \App\Models\LoyaltyPoint::where('customer_id', $customerId)
                ->where('salon_id', $salonId)
                ->with('offer')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'loyalty_history' => $points,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get loyalty history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply promo code (adds loyalty points)
     */
    public function applyPromo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $customerId = $request->attributes->get('customer_id');
        $salonId = $request->attributes->get('salon_id');

        try {
            // Check if promo code exists and is valid
            $offer = \App\Models\Offer::where('code', $validated['code'])
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (!$offer) {
                return response()->json([
                    'message' => 'Invalid or expired promo code',
                ], 400);
            }

            // Check if already redeemed
            $existingRedemption = \App\Models\LoyaltyPoint::where('customer_id', $customerId)
                ->where('offer_id', $offer->id)
                ->first();

            if ($existingRedemption) {
                return response()->json([
                    'message' => 'Promo code already used',
                ], 400);
            }

            // Apply promo (add loyalty points)
            $points = $offer->points_value ?? 0;
            if ($points > 0) {
                \App\Models\LoyaltyPoint::create([
                    'customer_id' => $customerId,
                    'salon_id' => $salonId,
                    'offer_id' => $offer->id,
                    'points' => $points,
                    'type' => 'promo',
                    'description' => "Promo code: {$offer->code}",
                ]);
            }

            return response()->json([
                'message' => 'Promo code applied successfully',
                'offer' => $offer,
                'points_added' => $points,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to apply promo code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get loyalty tier based on points
     */
    private function getLoyaltyTier(int $points): string
    {
        if ($points >= 1000) return 'Platinum';
        if ($points >= 500) return 'Gold';
        if ($points >= 100) return 'Silver';
        return 'Bronze';
    }
}
