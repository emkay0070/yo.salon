<?php

namespace App\Listeners;

use App\Domain\Finance\Events\RevenueDistributionRequested;
use App\Domain\Finance\RevenueDistribution\RevenueDistributionEngine;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

/**
 * ApplyRevenueDistribution runs the revenue distribution engine
 * when a RevenueDistributionRequested event is dispatched.
 *
 * This listener owns one responsibility:
 *   Load the booking and call RevenueDistributionEngine::applyForBooking().
 *
 * The engine itself is idempotent — calling it twice for the same booking
 * is a safe no-op. This listener does not need to guard against duplicates
 * at this level; the engine does it at the FinancialTransaction level.
 */
class ApplyRevenueDistribution
{
    public function __construct(
        protected RevenueDistributionEngine $engine
    ) {}

    public function handle(RevenueDistributionRequested $event): void
    {
        try {
            $booking = Booking::find($event->bookingId);

            if (!$booking) {
                Log::error('ApplyRevenueDistribution: booking not found', [
                    'booking_id' => $event->bookingId,
                ]);
                return;
            }

            $this->engine->applyForBooking($booking);

        } catch (\Exception $e) {
            Log::error('ApplyRevenueDistribution: failed to apply distribution', [
                'booking_id'     => $event->bookingId,
                'transaction_id' => $event->transactionId,
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            // Not re-thrown. Distribution failure should not fail the payment.
            // Unposted distributions will be caught by a scheduled reconciliation job.
        }
    }
}
