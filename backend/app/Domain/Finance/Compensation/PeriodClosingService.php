<?php

namespace App\Domain\Finance\Compensation;

use App\Domain\Finance\Settlement\Settlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PeriodClosingService handles the finalization of CompensationPeriods.
 *
 * Responsibilities:
 * 1. Find all open periods that have passed their end date.
 * 2. Generate period-sourced earnings for salary and hybrid-base policies
 *    that haven't been generated yet for the period.
 * 3. Lock the period (status = closing).
 * 4. Create the Settlement obligation.
 * 5. Mark period closed, mark all earnings payable.
 */
class PeriodClosingService
{
    /**
     * Run the closing process for all due periods.
     * Normally called by a daily scheduled job.
     */
    public function closeDuePeriods(\DateTimeInterface $asOf = null): void
    {
        $asOf ??= now();
        $date = $asOf instanceof Carbon ? $asOf->toDateString() : $asOf->format('Y-m-d');

        // Find periods due for closing
        $periods = CompensationPeriod::dueForClosing()
            ->where('period_end', '<', $date) // Ensure we only close strictly after the period ends
            ->get();

        foreach ($periods as $period) {
            try {
                $this->closePeriod($period);
            } catch (\Exception $e) {
                Log::error('Failed to close CompensationPeriod', [
                    'period_id' => $period->id,
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Manually close a specific period, e.g., for early termination.
     */
    public function closePeriod(CompensationPeriod $period, ?int $closedByUserId = null): void
    {
        DB::transaction(function () use ($period, $closedByUserId) {
            if ($period->status !== CompensationPeriod::STATUS_OPEN) {
                throw new \LogicException("Cannot close period: status is '{$period->status}'.");
            }

            // 1. Generate base salary earnings if applicable
            $this->generateSalaryEarnings($period);

            // 2. Lock the period
            $period->beginClosing();

            // If nothing is owed, we can just void the period
            if ($period->payable_amount <= 0 && $period->activeEarnings()->count() === 0) {
                $period->void();
                return;
            }

            // 3. Create the Settlement obligation
            $settlement = Settlement::create([
                'payable_type'    => $period->provider_type,
                'payable_id'      => $period->provider_id,
                'recipient_type'  => $period->compensatable_type,
                'recipient_id'    => $period->compensatable_id,
                'reference_type'  => CompensationPeriod::class,
                'reference_id'    => $period->id,
                'amount'          => $period->payable_amount,
                'currency'        => $period->currency,
                'status'          => Settlement::STATUS_PENDING,
                'scheduled_for'   => now(),
                'metadata'        => [
                    'source'    => 'compensation_period',
                    'period_id' => $period->id,
                    'policy_id' => $period->compensation_policy_id,
                ],
            ]);

            // 4. Finalize period state
            $period->markAsClosed($settlement->id, $closedByUserId);

            // 5. Mark all active earnings as payable
            $period->activeEarnings()->update([
                'status'        => Earning::STATUS_PAYABLE,
                'settlement_id' => $settlement->id,
            ]);

            Log::info('Period closed successfully', [
                'period_id'     => $period->id,
                'settlement_id' => $settlement->id,
                'amount'        => $period->payable_amount,
            ]);
        });
    }

    /**
     * Ensure period-sourced earnings (salary, hybrid base) are created.
     */
    private function generateSalaryEarnings(CompensationPeriod $period): void
    {
        $policy = $period->policy;

        if (!$policy) {
            return;
        }

        // We only generate base earnings if the policy is salary, fixed, or hybrid.
        if (!in_array($policy->type, ['salary', 'fixed', 'hybrid'])) {
            return;
        }

        // Check if we already generated a period-sourced earning for this period
        $hasBaseEarning = $period->earnings()
            ->where('source_type', CompensationPeriod::class)
            ->where('source_id', $period->id)
            ->exists();

        if ($hasBaseEarning) {
            return;
        }

        $baseAmount = $this->calculateBaseAmount($policy);

        if ($baseAmount > 0) {
            $earning = Earning::create([
                'compensatable_type'     => $period->compensatable_type,
                'compensatable_id'       => $period->compensatable_id,
                'source_type'            => CompensationPeriod::class,
                'source_id'              => $period->id,
                'compensation_policy_id' => $policy->id,
                'compensation_period_id' => $period->id,
                'gross_amount'           => $baseAmount,
                'currency'               => $period->currency,
                'status'                 => Earning::STATUS_INCLUDED,
                'metadata'               => [
                    'type' => 'period_base',
                ],
            ]);

            // We do not call period->recalculate() here because beginClosing() will do it shortly
        }
    }

    private function calculateBaseAmount(CompensationPolicy $policy): float
    {
        $rules = $policy->rules ?? [];

        if (in_array($policy->type, ['salary', 'fixed'])) {
            return (float) ($rules[0]['amount'] ?? 0);
        }

        if ($policy->type === 'hybrid') {
            foreach ($rules as $rule) {
                if (($rule['type'] ?? '') === 'base' || ($rule['source'] ?? '') === 'period') {
                    return (float) ($rule['amount'] ?? 0);
                }
            }
        }

        return 0.0;
    }
}
