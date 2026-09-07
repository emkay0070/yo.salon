<?php

namespace App\Listeners\Finance;

use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\Events\SettlementClosed;

class MarkCompensationPaid
{
    /**
     * Handle the event.
     * When a settlement is fully paid, mark the corresponding
     * CompensationPeriod and Earnings as settled/paid.
     */
    public function handle(SettlementClosed $event): void
    {
        // 1. Check if the settlement originated from an Earning (immediate)
        if (($event->metadata['source'] ?? null) === 'compensation_immediate' && isset($event->metadata['earning_id'])) {
            $earning = Earning::find($event->metadata['earning_id']);
            if ($earning && $earning->status === Earning::STATUS_PAYABLE) {
                $earning->markAsPaid();
            }
        }

        // 2. Check if the settlement originated from a CompensationPeriod
        if (($event->metadata['source'] ?? null) === 'compensation_period' && isset($event->metadata['period_id'])) {
            $period = CompensationPeriod::find($event->metadata['period_id']);
            if ($period && $period->status === CompensationPeriod::STATUS_CLOSED) {
                $period->markAsSettled();

                // Mark all payable earnings in the period as paid
                $period->activeEarnings()->where('status', Earning::STATUS_PAYABLE)->get()->each(function ($earning) {
                    $earning->markAsPaid();
                });
            }
        }
    }
}
