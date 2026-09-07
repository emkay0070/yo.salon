<?php

namespace App\Domain\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * EarningRecognized is dispatched by RevenueDistributionEngine for each party
 * that has earned a share of booking revenue.
 *
 * The event carries the ALREADY-CALCULATED amount — the listener is a recorder,
 * not a calculator. There is one single source of truth for the amount.
 *
 * The Compensation domain's RecordEarning listener handles this event.
 * It decides whether to create an immediate Settlement or include the
 * Earning in an open CompensationPeriod, based on the policy's schedule.
 */
class EarningRecognized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /** The type of the entity that earned this (e.g. App\Models\Specialist) */
        public readonly string $compensatableType,

        /** UUID of the earning entity */
        public readonly string $compensatableId,

        /** Source that triggered the earning (e.g. App\Models\Booking) */
        public readonly string $sourceType,

        /** UUID of the source */
        public readonly string $sourceId,

        /** UUID of the CompensationPolicy that was applied (snapshot) */
        public readonly string $policyId,

        /** Pre-calculated gross earning amount — listener does NOT recalculate */
        public readonly float $amount,

        /** Currency, defaults to UGX */
        public readonly string $currency = 'UGX',

        /** Full calculation trace from EarningCalculator */
        public readonly array $metadata = [],
    ) {}
}
