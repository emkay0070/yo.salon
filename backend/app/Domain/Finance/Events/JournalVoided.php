<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * JournalVoided event is dispatched when a journal is voided.
 */
class JournalVoided
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $journalId,
        public readonly string $journalNumber,
        public readonly string $reversingJournalId,
        public readonly array $metadata = []
    ) {}
}
