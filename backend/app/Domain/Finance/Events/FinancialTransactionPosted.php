<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FinancialTransactionPosted event is dispatched when a financial transaction is posted.
 */
class FinancialTransactionPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $transactionNumber,
        public readonly string $type,
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly ?string $referenceType = null,
        public readonly ?string $referenceId = null,
        public readonly array $metadata = []
    ) {}
}
