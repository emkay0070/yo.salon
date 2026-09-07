<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SettlementCreated event is dispatched when a settlement is created.
 * 
 * This event can be used for:
 * - Notifications to recipients
 * - Payment scheduling
 * - Analytics
 */
class SettlementCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $settlementId,
        public readonly string $recipientType,
        public readonly string $recipientId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $metadata = []
    ) {}
}
