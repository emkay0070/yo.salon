<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RevenueDistributed event is dispatched when revenue is distributed.
 * 
 * This event can be used for:
 * - Analytics
 * - Notifications
 * - Audit trails
 * - Reporting
 */
class RevenueDistributed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $bookingId,
        public readonly array $distributions,
        public readonly ?string $policyId = null,
        public readonly array $metadata = []
    ) {}
}
