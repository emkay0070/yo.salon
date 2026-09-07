<?php

namespace App\Listeners\Finance;

use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\Events\EarningRecognized;
use App\Domain\Finance\Settlement\Settlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RecordEarning listens for EarningRecognized and persists the Earning.
 *
 * It is purely a recorder — the amount in the event is final.
 * Based on the policy's settlement_frequency it either:
 *   - Creates a Settlement immediately (frequency = 'immediate')
 *   - Adds the Earning to an open CompensationPeriod (all other frequencies)
 *
 * Status transitions:
 *   immediate → earned → payable  (Settlement created)
 *   period    → earned → included (added to period)
 */
class RecordEarning
{
    public function handle(EarningRecognized $event): void
    {
        $policy = CompensationPolicy::find($event->policyId);

        if (!$policy) {
            Log::warning('RecordEarning: CompensationPolicy not found', [
                'policy_id' => $event->policyId,
            ]);
            return;
        }

        if (!$policy->auto_create_earnings) {
            Log::info('RecordEarning: auto_create_earnings disabled for policy', [
                'policy_id' => $event->policyId,
            ]);
            return;
        }

        DB::transaction(function () use ($event, $policy) {
            // 1. Create the Earning record
            $earning = Earning::create([
                'compensatable_type'     => $event->compensatableType,
                'compensatable_id'       => $event->compensatableId,
                'source_type'            => $event->sourceType,
                'source_id'              => $event->sourceId,
                'compensation_policy_id' => $event->policyId,
                'gross_amount'           => $event->amount,
                'currency'               => $event->currency,
                'status'                 => Earning::STATUS_EARNED,
                'metadata'               => $event->metadata,
            ]);

            // 2. Route based on settlement frequency
            if ($policy->isImmediate()) {
                $this->createImmediateSettlement($earning, $policy);
            } else {
                $this->addToPeriod($earning, $policy);
            }
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * For 'immediate' frequency: create a Settlement right away.
     * Earning goes: earned → payable
     */
    private function createImmediateSettlement(Earning $earning, CompensationPolicy $policy): void
    {
        $settlement = Settlement::create([
            'payable_type'    => $policy->provider_type,
            'payable_id'      => $policy->provider_id,
            'recipient_type'  => $earning->compensatable_type,
            'recipient_id'    => $earning->compensatable_id,
            'reference_type'  => $earning->source_type,
            'reference_id'    => $earning->source_id,
            'amount'          => $earning->gross_amount,
            'currency'        => $earning->currency,
            'status'          => Settlement::STATUS_PENDING,
            'scheduled_for'   => now(),
            'metadata'        => [
                'source'      => 'compensation_immediate',
                'earning_id'  => $earning->id,
                'policy_id'   => $earning->compensation_policy_id,
            ],
        ]);

        $earning->markAsPayable($settlement->id);

        Log::info('RecordEarning: immediate Settlement created', [
            'earning_id'    => $earning->id,
            'settlement_id' => $settlement->id,
            'amount'        => $earning->gross_amount,
        ]);
    }

    /**
     * For period-based frequencies: add to the current open period.
     * Earning goes: earned → included
     */
    private function addToPeriod(Earning $earning, CompensationPolicy $policy): void
    {
        $period = $this->getOrCreateOpenPeriod($earning, $policy);
        $earning->includeInPeriod($period);

        // Recompute the period's gross_amount
        $period->gross_amount = (float) $period->gross_amount + (float) $earning->gross_amount;
        $period->payable_amount = (float) $period->gross_amount + (float) $period->adjustments_total;
        $period->save();

        Log::info('RecordEarning: Earning added to period', [
            'earning_id' => $earning->id,
            'period_id'  => $period->id,
            'amount'     => $earning->gross_amount,
        ]);
    }

    /**
     * Find or create the open CompensationPeriod for this payee, provider, and frequency.
     */
    private function getOrCreateOpenPeriod(Earning $earning, CompensationPolicy $policy): CompensationPeriod
    {
        [$start, $end] = $this->resolvePeriodDates($policy);

        return CompensationPeriod::firstOrCreate(
            [
                'compensatable_type' => $earning->compensatable_type,
                'compensatable_id'   => $earning->compensatable_id,
                'provider_type'      => $policy->provider_type,
                'provider_id'        => $policy->provider_id,
                'period_start'       => $start,
                'period_end'         => $end,
            ],
            [
                'compensation_policy_id' => $policy->id,
                'gross_amount'           => 0,
                'adjustments_total'      => 0,
                'payable_amount'         => 0,
                'currency'               => $earning->currency,
                'status'                 => CompensationPeriod::STATUS_OPEN,
            ]
        );
    }

    /**
     * Calculate the start and end date of the current period based on frequency.
     *
     * @return array{0: string, 1: string}  [period_start, period_end] as date strings
     */
    private function resolvePeriodDates(CompensationPolicy $policy): array
    {
        $now = Carbon::now();

        return match ($policy->settlement_frequency) {
            'weekly'    => [
                $now->startOfWeek()->toDateString(),
                $now->copy()->endOfWeek()->toDateString(),
            ],
            'biweekly'  => $this->biweeklyDates($now),
            'monthly'   => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
            default     => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
        };
    }

    /**
     * Biweekly: 1st–15th or 16th–end of month.
     *
     * @return array{0: string, 1: string}
     */
    private function biweeklyDates(Carbon $now): array
    {
        if ($now->day <= 15) {
            return [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->startOfMonth()->addDays(14)->toDateString(),
            ];
        }

        return [
            $now->copy()->startOfMonth()->addDays(15)->toDateString(),
            $now->copy()->endOfMonth()->toDateString(),
        ];
    }
}
