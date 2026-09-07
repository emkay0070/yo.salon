<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CommissionCalculated event is dispatched when commission is calculated.
 * 
 * This event can be used for:
 * - Analytics
 * - Notifications
 * - Audit trails
 */
class CommissionCalculated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $bookingId,
        public readonly array $distributions,
        public readonly array $metadata = []
    ) {}
}
