<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RefundCaptured event is dispatched when a refund is confirmed in the Finance Domain.
 * 
 * This event triggers the Finance Domain to:
 * - Post the refund reversal journal
 * - Reverse ledger entries
 * - Update financial facts
 */
class RefundCaptured
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $originalTransactionId,
        public readonly string $bookingId,
        public readonly string $salonId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $metadata = []
    ) {}
}
