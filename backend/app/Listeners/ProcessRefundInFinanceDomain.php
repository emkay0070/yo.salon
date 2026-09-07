<?php

namespace App\Listeners;

use App\Events\RefundConfirmed;
use App\Domain\Finance\Events\RefundCaptured;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * ProcessRefundInFinanceDomain bridges the Payments Domain to the Finance Domain for refunds.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RESPONSIBILITY (single)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * This listener translates the Payments Domain event (RefundConfirmed) into
 * a Finance Domain event. It does NOT perform any financial operations itself.
 *
 * It dispatches:
 *
 *   RefundCaptured
 *      → Handled by PostJournalForPayment
 *      → Posts the refund reversal journal:
 *            DR Revenue / CR Asset / CR Processing Cost
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SEPARATION OF CONCERNS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * The Payments domain says: "A refund happened."
 * The Finance domain says: "A refund happened, therefore I need to reverse the financial entries."
 *
 * This listener maintains that boundary.
 */
class ProcessRefundInFinanceDomain
{
    /**
     * Handle the RefundConfirmed event.
     *
     * Does NOT throw. Refund was already successful; Finance processing is
     * secondary and must not roll back the refund record.
     */
    public function handle(RefundConfirmed $event): void
    {
        try {
            $originalTransaction = $event->originalTransaction;
            $refundTransaction = $event->refundTransaction;
            $booking = $event->booking;

            // ── Notify Finance Domain that a refund was captured ──────────────
            // PostJournalForPayment listens to this and posts the refund journal.
            Event::dispatch(new RefundCaptured(
                transactionId: $refundTransaction->id,
                originalTransactionId: $originalTransaction->id,
                bookingId: $booking->id,
                salonId: $event->salonId,
                amount: $event->refundAmount,
                currency: $event->currency,
                metadata: [
                    'customer_id' => $event->customerId,
                    'original_provider_reference' => $originalTransaction->provider_reference,
                    'refund_provider_reference' => $refundTransaction->provider_reference,
                ]
            ));

            Log::info('ProcessRefundInFinanceDomain: Finance event dispatched', [
                'booking_id' => $booking->id,
                'original_transaction_id' => $originalTransaction->id,
                'refund_transaction_id' => $refundTransaction->id,
                'amount' => $event->refundAmount,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessRefundInFinanceDomain: failed to dispatch Finance event', [
                'booking_id' => $event->booking->id ?? null,
                'refund_transaction_id' => $event->refundTransaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Intentionally not re-thrown. The refund is committed.
            // Finance processing will be replayed via event replay or manual trigger.
        }
    }
}
