<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * LedgerEntryCreated event is dispatched when a ledger entry is created.
 */
class LedgerEntryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $ledgerEntryId,
        public readonly string $ledgerAccountId,
        public readonly string $type,
        public readonly float $amount,
        public readonly float $balanceAfter,
        public readonly ?string $journalId = null,
        public readonly array $metadata = []
    ) {}
}
