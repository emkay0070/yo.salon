<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FinancialTransactionVoided event is dispatched when a financial transaction is voided.
 */
class FinancialTransactionVoided
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $transactionNumber,
        public readonly array $metadata = []
    ) {}
}
