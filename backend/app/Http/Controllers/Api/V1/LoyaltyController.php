<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoyaltyController extends Controller
{
    protected LoyaltyService $loyaltyService;

    public function __construct(LoyaltyService $loyaltyService)
    {
        $this->loyaltyService = $loyaltyService;
    }

    /**
     * Get customer's loyalty summary
     */
    public function summary(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        if (!$providerId || !$customerId) {
            return response()->json([
                'message' => 'Context not found',
            ], 400);
        }

        $summary = $this->loyaltyService->getCustomerSummary($customerId, $providerId);
        $benefits = $this->loyaltyService->getTierBenefits($summary['tier']);

        return response()->json([
            'loyalty' => $summary,
            'tier_benefits' => $benefits,
        ]);
    }

    /**
     * Get customer's loyalty balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        if (!$providerId || !$customerId) {
            return response()->json([
                'message' => 'Context not found',
            ], 400);
        }

        $summary = $this->loyaltyService->getCustomerSummary($customerId, $providerId);

        return response()->json([
            'balance' => $summary['balance'],
            'tier' => $summary['tier'],
        ]);
    }

    /**
     * Get customer's loyalty transaction history
     */
    public function history(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        if (!$providerId || !$customerId) {
            return response()->json([
                'message' => 'Context not found',
            ], 400);
        }

        $limit = $request->query('limit', 20);
        $history = $this->loyaltyService->getCustomerHistory($customerId, $providerId, $limit);

        return response()->json([
            'history' => $history,
        ]);
    }

    /**
     * Get customer's loyalty transaction history (alias for history)
     */
    public function getHistory(Request $request): JsonResponse
    {
        return $this->history($request);
    }

    /**
     * Redeem points
     */
    public function redeem(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        if (!$providerId || !$customerId) {
            return response()->json([
                'message' => 'Context not found',
            ], 400);
        }

        $validated = $request->validate([
            'points' => 'required|integer|min:1',
            'description' => 'required|string',
        ]);

        try {
            $transaction = $this->loyaltyService->redeemPoints(
                $customerId,
                $providerId,
                $validated['points'],
                $validated['description']
            );

            return response()->json([
                'message' => 'Points redeemed successfully',
                'transaction' => $transaction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to redeem points',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Award points for a booking (admin/internal use)
     */
    public function award(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|uuid',
        ]);

        try {
            $this->loyaltyService->awardPointsForBooking($validated['booking_id']);

            return response()->json([
                'message' => 'Points awarded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to award points',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
