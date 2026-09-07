<?php

namespace App\Listeners;

use App\Domain\Finance\Events\PaymentCaptured;
use App\Domain\Finance\Events\RefundCaptured;
use App\Domain\Finance\Journal\JournalPostingService;
use App\Models\Transaction;

class PostJournalForPayment
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected JournalPostingService $journalPostingService
    ) {}

    /**
     * Handle the PaymentCaptured event.
     */
    public function handlePaymentCaptured(PaymentCaptured $event): void
    {
        $transaction = Transaction::find($event->transactionId);
        
        if (!$transaction) {
            \Illuminate\Support\Facades\Log::error('PostJournalForPayment: Transaction not found', [
                'transaction_id' => $event->transactionId
            ]);
            return;
        }

        $this->journalPostingService->postForTransaction($transaction);
    }

    /**
     * Handle the RefundCaptured event.
     */
    public function handleRefundCaptured(RefundCaptured $event): void
    {
        $transaction = Transaction::find($event->transactionId);
        
        if (!$transaction) {
            \Illuminate\Support\Facades\Log::error('PostJournalForPayment: Refund transaction not found', [
                'transaction_id' => $event->transactionId
            ]);
            return;
        }

        $this->journalPostingService->postForTransaction($transaction);

        // Reverse revenue distribution if applicable
        $booking = $transaction->booking;
        if ($booking) {
            $reversalService = app(\App\Domain\Finance\RevenueDistribution\RevenueDistributionReversal::class);
            $reversalService->reverseForBooking($booking, $event->transactionId);
        }
    }
}
