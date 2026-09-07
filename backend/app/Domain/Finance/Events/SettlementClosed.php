<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SettlementClosed event is dispatched when a settlement is fully paid/closed.
 */
class SettlementClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $settlementId,
        public readonly string $payableType,
        public readonly string $payableId,
        public readonly string $recipientType,
        public readonly string $recipientId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $metadata = []
    ) {}
}
