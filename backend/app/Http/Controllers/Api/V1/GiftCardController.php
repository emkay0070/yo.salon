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

        $providerId = $request->attributes->get('provider_id');

        try {
            $giftCard = $this->giftCardService->validateCode($validated['code'], $providerId);

            if (!$giftCard) {
                return response()->json([
                    'message' => 'Invalid or expired gift card',
                ], 400);
            }

            return response()->json([
                'valid' => true,
                'amount' => $giftCard->amount,
                'balance' => $giftCard->balance,
                'currency' => $giftCard->currency,
                'status' => $giftCard->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to validate gift card',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redeem a gift card (full redemption)
     */
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $result = $this->giftCardService->redeemGiftCard($validated['code'], $customerId, $providerId);

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
     * Partially redeem a gift card
     */
    public function redeemPartial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $result = $this->giftCardService->redeemGiftCardPartial(
                $validated['code'],
                $customerId,
                $providerId,
                $validated['amount']
            );

            return response()->json([
                'message' => 'Gift card partially redeemed successfully',
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

        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $giftCard = $this->giftCardService->purchaseGiftCard(
                $customerId,
                $providerId,
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
        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $giftCards = $this->giftCardService->getCustomerPurchasedGiftCards($customerId, $providerId);

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
        $providerId = $request->attributes->get('provider_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $giftCards = $this->giftCardService->getCustomerRedeemedGiftCards($customerId, $providerId);

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

    /**
     * Get gift card transaction history
     */
    public function history(Request $request, string $giftCardId): JsonResponse
    {
        try {
            $history = $this->giftCardService->getTransactionHistory($giftCardId);

            return response()->json([
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get transaction history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a gift card (provider admin only)
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gift_card_id' => 'required|uuid',
            'reason' => 'required|string',
        ]);

        $performedBy = $request->attributes->get('user_id');

        try {
            $giftCard = $this->giftCardService->cancelGiftCard(
                $validated['gift_card_id'],
                $validated['reason'],
                $performedBy
            );

            return response()->json([
                'message' => 'Gift card cancelled successfully',
                'gift_card' => $giftCard,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel gift card',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Adjust gift card balance (provider admin only)
     */
    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gift_card_id' => 'required|uuid',
            'amount' => 'required|numeric',
            'reason' => 'required|string',
        ]);

        $performedBy = $request->attributes->get('user_id');

        try {
            $giftCard = $this->giftCardService->adjustBalance(
                $validated['gift_card_id'],
                $validated['amount'],
                $validated['reason'],
                $performedBy
            );

            return response()->json([
                'message' => 'Gift card balance adjusted successfully',
                'gift_card' => $giftCard,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to adjust gift card balance',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Issue/activate a gift card (provider admin only)
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gift_card_id' => 'required|uuid',
        ]);

        $performedBy = $request->attributes->get('user_id');

        try {
            $giftCard = $this->giftCardService->issueGiftCard($validated['gift_card_id'], $performedBy);

            return response()->json([
                'message' => 'Gift card issued successfully',
                'gift_card' => $giftCard,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to issue gift card',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
