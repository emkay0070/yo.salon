<?php

namespace App\Domain\Finance\RevenueDistribution;

use App\Domain\Finance\Compensation\CompensationAdjustment;
use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RevenueDistributionReversal handles the reversal of revenue distribution
 * when a refund occurs after revenue has been allocated to parties.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THREE REVERSAL STATES
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * State A: Earned (status = 'earned' or 'included')
 *   - Revenue distributed but no Settlement created
 *   - Action: Void the Earning
 *   - Ledger: Reverse revenue distribution journal
 *
 * State B: Payable (status = 'payable', Settlement exists)
 *   - Settlement created but no Payout completed
 *   - Action: Cancel Settlement, void Earning
 *   - Ledger: Reverse revenue distribution journal
 *
 * State C: Paid (status = 'paid', Payout completed)
 *   - Money already paid to specialist
 *   - Action: Create CompensationAdjustment (negative) for recovery
 *   - Ledger: Adjustment applied when period closes
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LEDGER REVERSAL
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Original distribution journal:
 *   DR Revenue Allocation  amount
 *   CR Payable (party)      amount
 *
 * Reversal journal:
 *   DR Payable (party)      amount
 *   CR Revenue Allocation  amount
 */
class RevenueDistributionReversal
{
    /**
     * Reverse revenue distribution for a refunded booking.
     *
     * @param Booking $booking The booking that was refunded
     * @param string $refundTransactionId The refund transaction ID
     * @return void
     */
    public function reverseForBooking(Booking $booking, string $refundTransactionId): void
    {
        // Find all earnings related to this booking
        $earnings = Earning::where('source_type', Booking::class)
            ->where('source_id', $booking->id)
            ->get();

        if ($earnings->isEmpty()) {
            Log::info('RevenueDistributionReversal: no earnings to reverse', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        DB::transaction(function () use ($earnings, $booking, $refundTransactionId) {
            foreach ($earnings as $earning) {
                $this->reverseEarning($earning, $booking, $refundTransactionId);
            }

            // Reverse the revenue distribution journal
            $this->reverseDistributionJournal($booking, $refundTransactionId);
        });
    }

    /**
     * Reverse a single earning based on its state.
     */
    protected function reverseEarning(Earning $earning, Booking $booking, string $refundTransactionId): void
    {
        match ($earning->status) {
            Earning::STATUS_EARNED, Earning::STATUS_INCLUDED => $this->reverseEarned($earning, $booking),
            Earning::STATUS_PAYABLE => $this->reversePayable($earning, $booking),
            Earning::STATUS_PAID => $this->reversePaid($earning, $booking, $refundTransactionId),
            default => Log::warning('RevenueDistributionReversal: earning in unexpected state', [
                'earning_id' => $earning->id,
                'status' => $earning->status,
            ]),
        };
    }

    /**
     * State A: Earned - Void the earning.
     */
    protected function reverseEarned(Earning $earning, Booking $booking): void
    {
        $earning->void();

        Log::info('RevenueDistributionReversal: voided earning', [
            'earning_id' => $earning->id,
            'booking_id' => $booking->id,
            'status' => $earning->status,
        ]);
    }

    /**
     * State B: Payable - Cancel settlement and void earning.
     */
    protected function reversePayable(Earning $earning, Booking $booking): void
    {
        if ($earning->settlement) {
            $earning->settlement->cancel();
        }

        $earning->void();

        Log::info('RevenueDistributionReversal: cancelled settlement and voided earning', [
            'earning_id' => $earning->id,
            'settlement_id' => $earning->settlement_id,
            'booking_id' => $booking->id,
        ]);
    }

    /**
     * State C: Paid - Create compensation adjustment for recovery.
     */
    protected function reversePaid(Earning $earning, Booking $booking, string $refundTransactionId): void
    {
        // Create a negative compensation adjustment
        $adjustment = CompensationAdjustment::create([
            'compensatable_type' => $earning->compensatable_type,
            'compensatable_id' => $earning->compensatable_id,
            'compensation_period_id' => $earning->compensation_period_id,
            'type' => CompensationAdjustment::TYPE_DEDUCTION,
            'amount' => -$earning->gross_amount,
            'currency' => $earning->currency,
            'description' => "Refund recovery for booking #{$booking->id}",
            'created_by' => null, // System-generated
        ]);

        // Update the period's adjustments total
        if ($earning->period) {
            $earning->period->adjustments_total = (float) $earning->period->adjustments_total + (float) $adjustment->amount;
            $earning->period->payable_amount = (float) $earning->period->gross_amount + (float) $earning->period->adjustments_total;
            $earning->period->save();
        }

        Log::info('RevenueDistributionReversal: created recovery adjustment for paid earning', [
            'earning_id' => $earning->id,
            'adjustment_id' => $adjustment->id,
            'booking_id' => $booking->id,
            'amount' => $adjustment->amount,
        ]);
    }

    /**
     * Reverse the revenue distribution journal entries.
     */
    protected function reverseDistributionJournal(Booking $booking, string $refundTransactionId): void
    {
        // Find the revenue distribution financial transaction
        $distributionFT = FinancialTransaction::where('type', 'revenue_distribution')
            ->where('reference_type', Booking::class)
            ->where('reference_id', $booking->id)
            ->first();

        if (!$distributionFT) {
            Log::info('RevenueDistributionReversal: no distribution journal to reverse', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        // Void the distribution financial transaction
        // This creates reversal journal entries automatically
        $distributionFT->void();

        Log::info('RevenueDistributionReversal: voided distribution journal', [
            'booking_id' => $booking->id,
            'ft_id' => $distributionFT->id,
            'refund_transaction_id' => $refundTransactionId,
        ]);
    }
}
