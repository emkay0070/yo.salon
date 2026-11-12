<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Customer;
use App\Models\Provider;
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
            $data['balance'] = $data['amount'] ?? 0;
            $data['status'] = 'created';
            
            $giftCard = GiftCard::create($data);

            // Record transaction
            $this->recordTransaction(
                $giftCard,
                'purchase',
                $giftCard->amount,
                $giftCard->balance,
                'Gift card purchased',
                $data['metadata'] ?? null,
                $data['performed_by'] ?? null
            );

            return $giftCard;
        });
    }

    /**
     * Issue/activate a gift card (make it available for use)
     */
    public function issueGiftCard(string $giftCardId, ?int $performedBy = null): GiftCard
    {
        return DB::transaction(function () use ($giftCardId, $performedBy) {
            $giftCard = GiftCard::lockForUpdate()->findOrFail($giftCardId);

            if ($giftCard->status !== 'created') {
                throw new \Exception('Gift card can only be issued from created status');
            }

            $giftCard->update([
                'status' => 'available',
                'issued_at' => now(),
            ]);

            $this->recordTransaction(
                $giftCard,
                'issue',
                0,
                $giftCard->balance,
                'Gift card issued/activated',
                null,
                $performedBy
            );

            return $giftCard->fresh();
        });
    }

    /**
     * Validate a gift card code
     */
    public function validateCode(string $code, string $providerId): ?GiftCard
    {
        $giftCard = GiftCard::findByCode($code, $providerId);

        if (!$giftCard || !$giftCard->isValid()) {
            return null;
        }

        return $giftCard;
    }

    /**
     * Redeem a gift card (full redemption)
     */
    public function redeemGiftCard(string $code, string $customerId, string $providerId): array
    {
        return $this->redeemGiftCardPartial($code, $customerId, $providerId, null);
    }

    /**
     * Partially redeem a gift card
     */
    public function redeemGiftCardPartial(string $code, string $customerId, string $providerId, ?float $amount = null): array
    {
        return DB::transaction(function () use ($code, $customerId, $providerId, $amount) {
            $giftCard = GiftCard::lockForUpdate()->where('code', $code)
                ->where('provider_id', $providerId)
                ->first();

            if (!$giftCard) {
                throw new \Exception('Gift card not found');
            }

            if (!$giftCard->isValid()) {
                throw new \Exception('Invalid or expired gift card');
            }

            $redeemAmount = $amount ?? $giftCard->balance;

            if ($redeemAmount <= 0) {
                throw new \Exception('Redemption amount must be greater than 0');
            }

            if (!$giftCard->canPartiallyRedeem($redeemAmount)) {
                throw new \Exception('Insufficient gift card balance');
            }

            $newBalance = $giftCard->balance - $redeemAmount;

            // Update status based on remaining balance
            $newStatus = $newBalance <= 0.01 ? 'fully_redeemed' : 'partially_redeemed';

            $giftCard->update([
                'balance' => $newBalance,
                'status' => $newStatus,
                'redeemed_by' => $customerId,
                'redeemed_at' => now(),
            ]);

            // Record transaction
            $this->recordTransaction(
                $giftCard,
                'redeem',
                $redeemAmount,
                $newBalance,
                'Gift card redemption',
                ['customer_id' => $customerId],
                null
            );

            return [
                'gift_card' => $giftCard->fresh(),
                'amount_redeemed' => $redeemAmount,
                'remaining_balance' => $newBalance,
            ];
        });
    }

    /**
     * Cancel a gift card
     */
    public function cancelGiftCard(string $giftCardId, string $reason, ?int $performedBy = null): GiftCard
    {
        return DB::transaction(function () use ($giftCardId, $reason, $performedBy) {
            $giftCard = GiftCard::lockForUpdate()->findOrFail($giftCardId);

            if ($giftCard->status === 'cancelled' || $giftCard->status === 'fully_redeemed') {
                throw new \Exception('Gift card cannot be cancelled in current status');
            }

            $giftCard->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'active' => false,
            ]);

            $this->recordTransaction(
                $giftCard,
                'cancellation',
                0,
                $giftCard->balance,
                $reason,
                null,
                $performedBy
            );

            return $giftCard->fresh();
        });
    }

    /**
     * Adjust gift card balance (admin operation)
     */
    public function adjustBalance(string $giftCardId, float $amount, string $reason, ?int $performedBy = null): GiftCard
    {
        return DB::transaction(function () use ($giftCardId, $amount, $reason, $performedBy) {
            $giftCard = GiftCard::lockForUpdate()->findOrFail($giftCardId);

            if ($giftCard->status === 'cancelled' || $giftCard->status === 'expired') {
                throw new \Exception('Cannot adjust cancelled or expired gift card');
            }

            $newBalance = $giftCard->balance + $amount;

            if ($newBalance < 0) {
                throw new \Exception('Balance cannot be negative');
            }

            $giftCard->update(['balance' => $newBalance]);

            $this->recordTransaction(
                $giftCard,
                'adjustment',
                $amount,
                $newBalance,
                $reason,
                null,
                $performedBy
            );

            return $giftCard->fresh();
        });
    }

    /**
     * Get gift cards purchased by a customer
     */
    public function getCustomerPurchasedGiftCards(string $customerId, string $providerId): array
    {
        $giftCards = GiftCard::where('purchased_by', $customerId)
            ->where('provider_id', $providerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $giftCards->map(function ($giftCard) {
            return [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'amount' => $giftCard->amount,
                'balance' => $giftCard->balance,
                'status' => $giftCard->status,
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
    public function getCustomerRedeemedGiftCards(string $customerId, string $providerId): array
    {
        $giftCards = GiftCard::where('redeemed_by', $customerId)
            ->where('provider_id', $providerId)
            ->orderBy('redeemed_at', 'desc')
            ->get();

        return $giftCards->map(function ($giftCard) {
            return [
                'id' => $giftCard->id,
                'code' => $giftCard->code,
                'amount' => $giftCard->amount,
                'balance' => $giftCard->balance,
                'status' => $giftCard->status,
                'redeemed_at' => $giftCard->redeemed_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get gift card transaction history
     */
    public function getTransactionHistory(string $giftCardId): array
    {
        $transactions = GiftCardTransaction::where('gift_card_id', $giftCardId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'description' => $transaction->description,
                'metadata' => $transaction->metadata,
                'created_at' => $transaction->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Initialize a gift card purchase (returns intent)
     */
    public function purchaseGiftCard(string $customerId, string $providerId, float $amount, ?string $message = null): \App\Models\GiftCardPurchaseIntent
    {
        return DB::transaction(function () use ($customerId, $providerId, $amount, $message) {
            return \App\Models\GiftCardPurchaseIntent::create([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
                'amount' => $amount,
                'currency' => 'UGX',
                'status' => 'pending',
                'message' => $message,
            ]);
        });
    }

    /**
     * Handle payment webhook confirmation
     */
    public function confirmPayment(string $intentId, string $paymentMethod, string $providerReference): void
    {
        $intent = \App\Models\GiftCardPurchaseIntent::lockForUpdate()->find($intentId);

        if (!$intent) {
            throw new \Exception('Purchase intent not found');
        }

        if ($intent->status === 'paid') {
            return; // Idempotent
        }

        $intent->update([
            'payment_method' => $paymentMethod,
            'provider_reference' => $providerReference,
        ]);

        event(new \App\Events\GiftCardPaymentConfirmed($intent));
    }
    
    /**
     * Handle payment webhook failure
     */
    public function failPayment(string $intentId, string $paymentMethod, string $providerReference): void
    {
        $intent = \App\Models\GiftCardPurchaseIntent::lockForUpdate()->find($intentId);

        if (!$intent || $intent->status !== 'pending') {
            return;
        }

        $intent->update([
            'status' => 'failed',
            'payment_method' => $paymentMethod,
            'provider_reference' => $providerReference,
        ]);

        event(new \App\Events\GiftCardPaymentFailed($intent));
    }

    /**
     * Record a transaction for a gift card
     */
    private function recordTransaction(
        GiftCard $giftCard,
        string $type,
        float $amount,
        float $balanceAfter,
        ?string $description,
        ?array $metadata,
        ?int $performedBy
    ): void {
        GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => $performedBy,
        ]);
    }
}
