<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RevenueDistributionRequested is dispatched after a payment journal is posted.
 *
 * This event cleanly separates two distinct economic events:
 *
 *   PaymentCaptured
 *       "Money was received by the salon."
 *       → Payment journal: DR Asset / CR Revenue
 *
 *   RevenueDistributionRequested
 *       "The revenue earned must now be allocated to the parties who earned it."
 *       → Distribution journal: DR Revenue Allocation / CR Payable (per party)
 *
 * Keeping these separate means:
 *  - Payment recognition can be retried independently of distribution.
 *  - Commission entitlement timing can be controlled separately (e.g. after
 *    service completion rather than at payment time).
 *  - The idempotency guard on each financial transaction reference prevents
 *    double-posting either leg on retry.
 */
class RevenueDistributionRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /**
         * The booking for which revenue should be distributed.
         */
        public readonly string $bookingId,

        /**
         * The salon receiving the revenue.
         */
        public readonly string $salonId,

        /**
         * Gross amount of the payment.
         */
        public readonly float $amount,

        public readonly string $currency,

        /**
         * The originating payment transaction ID.
         * Used to link distribution back to the payment for reconciliation.
         */
        public readonly string $transactionId,

        public readonly array $metadata = []
    ) {}
}
