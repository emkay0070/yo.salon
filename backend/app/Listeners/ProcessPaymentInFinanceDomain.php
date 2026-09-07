<?php

namespace App\Listeners;

use App\Events\PaymentConfirmed;
use App\Domain\Finance\Events\PaymentCaptured;
use App\Domain\Finance\Events\RevenueDistributionRequested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * ProcessPaymentInFinanceDomain bridges the Payments Domain to the Finance Domain.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RESPONSIBILITY (single)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * This listener translates the Payments Domain event (PaymentConfirmed) into
 * two Finance Domain events. It does NOT perform any financial operations itself.
 *
 * It dispatches:
 *
 *   1. PaymentCaptured
 *      → Handled by PostJournalForPayment
 *      → Posts the payment recognition journal:
 *            DR Asset / DR Processing Cost / CR Service Revenue
 *
 *   2. RevenueDistributionRequested
 *      → Handled by ApplyRevenueDistribution
 *      → Posts the distribution journal:
 *            DR Revenue Allocation / CR Payable (per party)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SEPARATION OF CONCERNS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Previously this listener called RevenueDistributionEngine::applyForBooking()
 * directly — mixing "payment recognition" and "revenue allocation" in a single
 * synchronous call. That pattern:
 *
 *   • Made retries double-post distribution (no idempotency on the direct call)
 *   • Prevented independent retry/replay of the two financial operations
 *   • Coupled the distribution timing to payment time (prevents future
 *     "distribute after service completion" logic)
 *
 * The event-driven approach fixes all three.
 */
class ProcessPaymentInFinanceDomain
{
    /**
     * Handle the PaymentConfirmed event.
     *
     * Does NOT throw. Payment was already successful; Finance processing is
     * secondary and must not roll back the payment record.
     */
    public function handle(PaymentConfirmed $event): void
    {
        try {
            $booking     = $event->booking;
            $transaction = $event->transaction;

            $transactionId = $transaction->id ?? '';
            $salonId       = $event->salonId ?? $booking->salon_id;
            $amount        = $event->amount ?? $transaction->gross_amount;

            // ── 1. Notify Finance Domain that a payment was captured ────────
            // PostJournalForPayment listens to this and posts the payment journal.
            Event::dispatch(new PaymentCaptured(
                transactionId: $transactionId,
                bookingId:     $booking->id,
                salonId:       $salonId,
                amount:        $amount,
                currency:      'UGX',
                metadata: [
                    'customer_id'    => $event->customerId,
                    'payment_method' => $transaction->paymentMethod?->display_name ?? null,
                ]
            ));

            // ── 2. Request revenue distribution ────────────────────────────
            // ApplyRevenueDistribution listens to this and runs the engine.
            // Distribution is idempotent; firing twice is safe.
            Event::dispatch(new RevenueDistributionRequested(
                bookingId:     $booking->id,
                salonId:       $salonId,
                amount:        $amount,
                currency:      'UGX',
                transactionId: $transactionId,
                metadata: [
                    'customer_id' => $event->customerId,
                ]
            ));

            Log::info('ProcessPaymentInFinanceDomain: Finance events dispatched', [
                'booking_id'     => $booking->id,
                'transaction_id' => $transactionId,
                'amount'         => $amount,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessPaymentInFinanceDomain: failed to dispatch Finance events', [
                'booking_id' => $event->booking->id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            // Intentionally not re-thrown. The payment is committed.
            // Finance processing will be replayed via event replay or manual trigger.
        }
    }
}
