<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\Customer;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;

class GiftCardService
{
    /**
     * Create a new gift card
     */
    public function createGiftCard(array $data): GiftCard
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = GiftCard::generateCode();
            return GiftCard::create($data);
        });
    }

    /**
     * Validate a gift card code
     */
    public function validateCode(string $code, string $salonId): ?GiftCard
    {
        $giftCard = GiftCard::where('code', $code)
            ->where('salon_id', $salonId)
            ->first();

        if (!$giftCard || !$giftCard->isValid()) {
            return null;
        }

        return $giftCard;
    }

    /**
     * Redeem a gift card
     */
    public function redeemGiftCard(string $code, string $customerId, string $salonId): array
    {
        return DB::transaction(function () use ($code, $customerId, $salonId) {
            $giftCard = $this->validateCode($code, $salonId);

            if (!$giftCard) {
                throw new \Exception('Invalid or expired gift card');
            }

            // Add funds to customer wallet
            $walletService = new WalletService();
            $walletService->addFunds(
                $customerId,
                $salonId,
                $giftCard->amount,
                'Gift card redemption: ' . $giftCard->code,
                ['gift_card_id' => $giftCard->id]
            );

            // Mark gift card as redeemed
            $giftCard->update([
                'redeemed_by' => $customerId,
                'redeemed_at' => now(),
            ]);

            return [
                'gift_card' => $giftCard,
                'amount' => $giftCard->amount,
            ];
        });
    }

    /**
     * Get gift cards purchased by a customer
     */
    public function getCustomerPurchasedGiftCards(string $customerId, string $salonId): array
    {
        $giftCards = GiftCard::where('purchased_by', $customerId)
            ->where('salon_id', $salonId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $giftCards->map(function ($giftCard) {
            return [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'amount' => $giftCard->amount,
                'redeemed' => $giftCard->redeemed_at !== null,
                'redeemed_at' => $giftCard->redeemed_at?->toIso8601String(),
                'expires_at' => $giftCard->expires_at?->toIso8601String(),
                'message' => $giftCard->message,
            ];
        })->toArray();
    }

    /**
     * Get gift cards redeemed by a customer
     */
    public function getCustomerRedeemedGiftCards(string $customerId, string $salonId): array
    {
        $giftCards = GiftCard::where('redeemed_by', $customerId)
            ->where('salon_id', $salonId)
            ->orderBy('redeemed_at', 'desc')
            ->get();

        return $giftCards->map(function ($giftCard) {
            return [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'amount' => $giftCard->amount,
                'redeemed_at' => $giftCard->redeemed_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Purchase a gift card
     */
    public function purchaseGiftCard(string $customerId, string $salonId, float $amount, ?string $message = null): GiftCard
    {
        return DB::transaction(function () use ($customerId, $salonId, $amount, $message) {
            // Process payment (would integrate with payment processing)
            
            // Create gift card
            return $this->createGiftCard([
                'salon_id' => $salonId,
                'amount' => $amount,
                'purchased_by' => $customerId,
                'message' => $message,
            ]);
        });
    }
}
