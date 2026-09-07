<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * JournalPosted event is dispatched when a journal is posted.
 */
class JournalPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $journalId,
        public readonly string $journalNumber,
        public readonly string $type,
        public readonly float $totalDebit,
        public readonly float $totalCredit,
        public readonly ?string $referenceType = null,
        public readonly ?string $referenceId = null,
        public readonly array $metadata = []
    ) {}
}
