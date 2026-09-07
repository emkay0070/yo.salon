<?php

namespace App\Domain\Finance\RevenueDistribution;

use App\Models\Booking;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Events\RevenueDistributed;
use App\Domain\Finance\Events\EarningRecognized;
use Illuminate\Support\Facades\Log;

/**
 * RevenueDistributionEngine allocates earned revenue to the parties who earned it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ACCOUNTING MODEL
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * By the time this engine runs, the payment journal has already recorded:
 *
 *   DR  Salon Asset (cash / mobile_money / bank)    net_amount
 *   DR  Processing Cost                             gateway_fee
 *   CR  Service Revenue                             gross_amount
 *
 * The Service Revenue account now holds the full gross amount.
 * This engine answers: "Who has an economic claim on that revenue?"
 *
 * For each party that earns a share (specialist, supplier, …) we record:
 *
 *   DR  Revenue Allocation   party_amount   ← reduces salon's revenue
 *   CR  Payable (party)      party_amount   ← creates an obligation
 *
 * This keeps the balance sheet correct:
 *   • Revenue Allocation is a contra-revenue account (debit-normal).
 *     It reduces gross revenue to net salon revenue.
 *   • Payable represents what the salon owes; it is credit-normal.
 *
 * The journal balances because every DR Revenue Allocation is matched
 * by an equal CR to the corresponding party's payable.
 *
 * If there is only one party (the salon itself), there is nothing to
 * distribute and this engine exits early — no journal is created.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * IDEMPOTENCY
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * One booking produces at most one revenue_distribution FinancialTransaction.
 * The DB-level unique constraint on (reference_type, reference_id) enforces this.
 * We also do an explicit exists() check before any work so retries are silent.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMPENSATION BOUNDARY
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * This engine's responsibility ends at EarningRecognized.
 * It does NOT create Earnings, Settlements, or Payouts.
 *
 * The Compensation domain (RecordEarning listener) decides:
 *   - immediate schedule → create Settlement now
 *   - period schedule    → add to CompensationPeriod
 *
 * Settlement → Payout is owned entirely by the Compensation domain.
 */
class RevenueDistributionEngine
{
    // ── Distribution reference type used for idempotency guard ──────────────
    private const REFERENCE_TYPE = Booking::class;

    /**
     * Apply revenue distribution for a completed booking.
     *
     * Idempotent: calling this twice for the same booking is a no-op.
     */
    public function applyForBooking(Booking $booking): void
    {
        // ── Idempotency guard ───────────────────────────────────────────────
        // One booking = one revenue_distribution FinancialTransaction, ever.
        if (FinancialTransaction::where('type', 'revenue_distribution')
            ->where('reference_type', self::REFERENCE_TYPE)
            ->where('reference_id', $booking->id)
            ->exists()) {
            Log::info('RevenueDistributionEngine: distribution already posted', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        // ── Resolve policy ──────────────────────────────────────────────────
        $policy = $this->getApplicablePolicy($booking, 'booking');

        if (!$policy) {
            Log::info('RevenueDistributionEngine: no applicable policy, skipping', [
                'booking_id' => $booking->id,
            ]);
            return;
        }

        // ── Calculate distributions ─────────────────────────────────────────
        $distributions = $this->calculateDistributions($booking, $policy);

        // If there are no third-party recipients, nothing to distribute
        if (empty($distributions)) {
            return;
        }

        // ── Build balanced journal movements ────────────────────────────────
        //
        //   For each party:
        //     DR Revenue Allocation  amount  ← reduces salon gross revenue
        //     CR Payable (party)     amount  ← creates obligation
        //
        // Every DR is matched by exactly one CR for the same amount → balanced.

        $movements = [];

        $revenueAllocationAccount = LedgerAccount::getOrCreateFor(
            \App\Models\Salon::class,
            $booking->salon_id,
            'revenue_allocation', // contra-revenue, debit-normal
            'UGX'
        );

        foreach ($distributions as $distribution) {
            $amount    = $distribution['amount'];
            $ownerType = $distribution['owner_type'];
            $ownerId   = $distribution['owner_id'];

            if (!$ownerType || !$ownerId || $amount <= 0) {
                continue;
            }

            // DR Revenue Allocation (reduces salon's recognised revenue)
            $movements[] = [
                'ledger_account_id' => $revenueAllocationAccount->id,
                'type'              => 'debit',
                'amount'            => $amount,
                'description'       => "Revenue allocation for booking #{$booking->id}",
                'metadata'          => [
                    'booking_id'  => $booking->id,
                    'party'       => $distribution['party'],
                    'policy_id'   => $policy->id,
                ],
            ];

            // CR Payable (party) — salon now owes this party
            $payableAccount = LedgerAccount::getOrCreateFor(
                $ownerType,
                $ownerId,
                'payable',
                'UGX'
            );

            $movements[] = [
                'ledger_account_id' => $payableAccount->id,
                'type'              => 'credit',
                'amount'            => $amount,
                'description'       => "Revenue share from booking #{$booking->id}",
                'metadata'          => [
                    'booking_id'  => $booking->id,
                    'party'       => $distribution['party'],
                    'policy_id'   => $policy->id,
                ],
            ];

            // ── Emit EarningRecognized — Compensation domain handles downstream ──
            // We only emit EarningRecognized for parties that need to be paid out (specialists, suppliers).
            // The platform is billed differently, and the salon keeps its own share.
            if (in_array($distribution['party'], ['specialist', 'supplier'])) {
                event(new EarningRecognized(
                    compensatableType: $ownerType,
                    compensatableId:   $ownerId,
                    sourceType:        Booking::class,
                    sourceId:          $booking->id,
                    policyId:          $policy->id,
                    amount:            $amount,
                    currency:          'UGX',
                    metadata:          [
                        'party'                   => $distribution['party'],
                        'distribution_type'       => $distribution['type'],
                        'distribution_percentage' => $distribution['percentage'] ?? null,
                        'booking_id'              => $booking->id,
                    ],
                ));
            }
        }

        if (empty($movements)) {
            return;
        }

        // ── Post the distribution financial transaction ──────────────────────
        FinancialTransaction::createWithJournals(
            type:          'revenue_distribution',
            description:   "Revenue distribution for booking #{$booking->id}",
            journalData: [
                [
                    'type'        => 'revenue_distribution',
                    'description' => "Revenue distribution for booking #{$booking->id}",
                    'movements'   => $movements,
                ],
            ],
            referenceType: self::REFERENCE_TYPE,
            referenceId:   $booking->id
        );

        // ── Dispatch event for analytics / audit ────────────────────────────
        event(new RevenueDistributed(
            bookingId:     $booking->id,
            distributions: $distributions,
            policyId:      $policy->id
        ));
    }

    /**
     * Calculate what each party is owed, without creating any records.
     *
     * @return array{party: string, amount: float, owner_type: string|null,
     *               owner_id: string|null, type: string, percentage: float|null}[]
     */
    public function calculateDistributions(Booking $booking, ?RevenueDistributionPolicy $policy = null): array
    {
        $policy ??= $this->getApplicablePolicy($booking, 'booking');

        if (!$policy) {
            return [];
        }

        $totalAmount = (float) ($booking->amount_due ?? $booking->amount_paid ?? 0);

        if ($totalAmount <= 0) {
            return [];
        }

        $distributions = [];

        foreach ($policy->rules as $rule) {
            $party  = $rule['party'] ?? null;
            $amount = $this->calculateAmount($totalAmount, $rule);

            // Skip the "payable" party (the salon itself) — that's already in the
            // payment journal's revenue credit. Also skip zero amounts.
            if (!$party || $party === 'payable' || $amount <= 0) {
                continue;
            }

            $ownerId = $this->resolveOwnerId($booking, $rule);

            if (!$ownerId) {
                continue;
            }

            $distributions[] = [
                'party'      => $party,
                'amount'     => $amount,
                'percentage' => $rule['percentage'] ?? null,
                'type'       => $rule['type'] ?? 'percentage',
                'owner_type' => $rule['owner_type'] ?? null,
                'owner_id'   => $ownerId,
            ];
        }

        return $distributions;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Protected helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function getApplicablePolicy(Booking $booking, string $revenueType): ?RevenueDistributionPolicy
    {
        $policy = RevenueDistributionPolicy::getApplicablePolicy(
            \App\Models\Salon::class,
            $booking->salon_id,
            $revenueType
        );

        return $policy ?? RevenueDistributionPolicy::getDefaultPolicy($revenueType);
    }

    protected function resolveOwnerId(Booking $booking, array $rule): ?string
    {
        return match ($rule['party'] ?? null) {
            'specialist' => $booking->specialist_id,
            'supplier'   => $booking->supplier_id ?? null,
            'platform'   => 'platform',
            default      => null,
        };
    }

    protected function calculateAmount(float $total, array $rule): float
    {
        return match ($rule['type'] ?? null) {
            'fixed'      => (float) ($rule['fixed'] ?? 0),
            'percentage' => $total * (((float) ($rule['percentage'] ?? 0)) / 100),
            default      => 0.0,
        };
    }

}
