<?php

namespace App\Listeners;

use App\Events\GiftCardPaymentConfirmed;
use App\Models\GiftCardPurchaseIntent;
use App\Domain\Finance\Journal\JournalEntry;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessGiftCardPaymentInFinanceDomain
{
    protected GiftCardService $giftCardService;

    public function __construct(GiftCardService $giftCardService)
    {
        $this->giftCardService = $giftCardService;
    }

    /**
     * Handle the event.
     */
    public function handle(GiftCardPaymentConfirmed $event): void
    {
        try {
            DB::transaction(function () use ($event) {
                // 1. Lock the intent for idempotency
                $intent = GiftCardPurchaseIntent::lockForUpdate()->find($event->intent->id);

                if (!$intent) {
                    Log::error("GiftCardPurchaseIntent not found", ['id' => $event->intent->id]);
                    return;
                }

                // If already paid, safe no-op.
                if ($intent->status === 'paid') {
                    Log::info("GiftCardPurchaseIntent already paid. Idempotent return.", ['id' => $intent->id]);
                    return;
                }

                // 2. Post Finance Journal (Deferred Revenue)
                $this->postPurchaseJournal($intent);

                // 3. Create and Issue Gift Card
                $giftCard = $this->giftCardService->createGiftCard([
                    'provider_id' => $intent->provider_id,
                    'amount' => $intent->amount,
                    'purchased_by' => $intent->customer_id,
                    'message' => $intent->message,
                    'currency' => $intent->currency,
                    'metadata' => ['purchase_intent_id' => $intent->id]
                ]);

                // Auto-issue
                $giftCard = $this->giftCardService->issueGiftCard($giftCard->id);

                // 4. Mark Intent as Paid
                $intent->update([
                    'status' => 'paid',
                    'gift_card_id' => $giftCard->id,
                ]);

                Log::info('GiftCard payment successfully processed and card issued.', [
                    'intent_id' => $intent->id,
                    'gift_card_id' => $giftCard->id,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('ProcessGiftCardPaymentInFinanceDomain: Failed to process', [
                'intent_id' => $event->intent->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function postPurchaseJournal(GiftCardPurchaseIntent $intent): void
    {
        $providerType = \App\Models\Provider::class;
        
        // Debit: The asset account receiving the money (e.g. mobile_money, bank, cash)
        $assetAccountType = $intent->payment_method ?? 'mobile_money';
        
        // Credit: Deferred revenue (payable)
        $liabilityAccountType = 'payable';

        $assetAccount = LedgerAccount::getOrCreateFor($providerType, $intent->provider_id, $assetAccountType, $intent->currency);
        $liabilityAccount = LedgerAccount::getOrCreateFor($providerType, $intent->provider_id, $liabilityAccountType, $intent->currency);

        $reference = 'GC-PURCHASE-' . Str::random(8);

        JournalEntry::createWithEntries(
            type: 'gift_card_purchase',
            description: "Gift Card Purchase - Intent {$intent->id}",
            movements: [
                [
                    'ledger_account_id' => $assetAccount->id,
                    'type' => 'debit',
                    'amount' => $intent->amount,
                    'description' => "Received funds for Gift Card",
                ],
                [
                    'ledger_account_id' => $liabilityAccount->id,
                    'type' => 'credit',
                    'amount' => $intent->amount,
                    'description' => "Deferred Revenue for Unredeemed Gift Card",
                ]
            ],
            referenceType: get_class($intent),
            referenceId: $intent->id
        );
    }
}
