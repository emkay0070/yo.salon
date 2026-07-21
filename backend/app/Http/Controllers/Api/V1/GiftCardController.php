<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GiftCardController extends Controller
{
    protected GiftCardService $giftCardService;

    public function __construct(GiftCardService $giftCardService)
    {
        $this->giftCardService = $giftCardService;
    }

    /**
     * Validate a gift card code
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $salonId = $request->attributes->get('salon_id');

        try {
            $giftCard = $this->giftCardService->validateCode($validated['code'], $salonId);

            if (!$giftCard) {
                return response()->json([
                    'message' => 'Invalid or expired gift card',
                ], 400);
            }

            return response()->json([
                'valid' => true,
                'amount' => $giftCard->amount,
                'currency' => $giftCard->currency,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to validate gift card',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redeem a gift card
     */
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $result = $this->giftCardService->redeemGiftCard($validated['code'], $customerId, $salonId);

            return response()->json([
                'message' => 'Gift card redeemed successfully',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to redeem gift card',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Purchase a gift card
     */
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'message' => 'nullable|string',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $giftCard = $this->giftCardService->purchaseGiftCard(
                $customerId,
                $salonId,
                $validated['amount'],
                $validated['message'] ?? null
            );

            return response()->json([
                'message' => 'Gift card purchased successfully',
                'gift_card' => $giftCard,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to purchase gift card',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer's purchased gift cards
     */
    public function purchased(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $giftCards = $this->giftCardService->getCustomerPurchasedGiftCards($customerId, $salonId);

            return response()->json([
                'gift_cards' => $giftCards,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get purchased gift cards',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer's redeemed gift cards
     */
    public function redeemed(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $giftCards = $this->giftCardService->getCustomerRedeemedGiftCards($customerId, $salonId);

            return response()->json([
                'gift_cards' => $giftCards,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get redeemed gift cards',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
