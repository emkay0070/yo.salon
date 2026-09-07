<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentCaptured event is dispatched when a payment is confirmed.
 * 
 * This event triggers the Finance Domain to:
 * - Apply revenue distribution rules
 * - Create ledger entries
 * - Create settlements
 */
class PaymentCaptured
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $bookingId,
        public readonly string $salonId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $metadata = []
    ) {}
}
