<?php

namespace App\Domain\Finance\Payout;

use App\Domain\Finance\Payout\Execution\ExecutionStrategy;
use App\Domain\Finance\Payout\Execution\ManualExecution;
use App\Domain\Finance\Payout\Execution\AutomatedExecution;
use App\Domain\Finance\Payout\Exceptions\AmbiguousDisbursementOutcomeException;
use App\Domain\Finance\Payout\Exceptions\NonRetryableDisbursementException;
use App\Domain\Finance\Payout\Exceptions\RetryableDisbursementException;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Orchestrates payout lifecycle: initiate → state-guard → execute → reconcile.
 *
 * State machine (§5.1.1 + Safeguard 1 ambiguous-state):
 *   pending ─► queued ─► processing ─┬─► completed
 *              ▲          │           ├─► failed (retryable) ─► queued ─► …
 *              │          │           ├─► failed_final (never retry)
 *              │          │           └─► ambiguous (block retries; reconcile only)
 *              │          └─► processing (idempotent in-flight no-op)
 *              └─ cancelled (pending only)
 *
 * Duplicate-transfer protection (3 independent layers):
 *   1. Atomic guard in executePayout step 1 (row-locked short TX).
 *   2. DB unique index on payouts.idempotency_key (create-time dedup).
 *   3. Terminal-state early return (completed → safe no-op).
 */
class PayoutService
{
    public function initiatePayout(
        Settlement $settlement,
        float $amount,
        string $method,
        string $recipientType,
        string $recipientId,
        string $executionMode = 'manual',
        ?User $processedBy = null,
        array $payload = []
    ): Payout {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than 0');
        }

        /** @var Payout $payout */
        $payout = DB::transaction(function () use (
            $settlement, $amount, $method, $recipientType, $recipientId,
            $executionMode, $processedBy
        ) {
            $lockedSettlement = Settlement::where('id', $settlement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pendingAndCompleted = (clone $lockedSettlement->payouts())
                ->whereIn('status', [
                    Payout::STATUS_PENDING,
                    Payout::STATUS_QUEUED,
                    Payout::STATUS_PROCESSING,
                    Payout::STATUS_AMBIGUOUS,
                    Payout::STATUS_COMPLETED,
                ])
                ->sum('amount');

            $remaining = $lockedSettlement->amount - $pendingAndCompleted;
            if ($amount > ($remaining + 0.001)) {
                throw new RuntimeException(sprintf(
                    'Insufficient settlement remaining balance. Requested: %s, remaining: %s',
                    $amount,
                    $remaining
                ));
            }

            $idempotencyKey = (string) Str::uuid();

            return Payout::create([
                'idempotency_key'       => $idempotencyKey,
                'settlement_id'         => $settlement->id,
                'recipient_type'        => $recipientType,
                'recipient_id'          => $recipientId,
                'amount'                => $amount,
                'currency'              => $settlement->currency ?? 'UGX',
                'method'                => $method,
                'execution_mode'        => $executionMode,
                'status'                => Payout::STATUS_PENDING,
                'attempt_count'         => 0,
                'processed_by'          => $processedBy?->id,
                'initiated_at'          => now(),
                'notes'                 => null,
                'metadata'              => [],
            ]);
        });

        // ── Transaction closed; settlement lock released BEFORE external call ──
        return $this->executePayout($payout, $payload);
    }

    /**
     * Execute a payout. Fully idempotent: safe to call multiple times for the
     * same payout. Callers: initiatePayout (inline), ExecutePayoutJob (async),
     * ReconcileProcessingPayouts (retry after failed).
     */
    public function executePayout(Payout $payout, array $payload = []): Payout
    {
        // ────────────────────────────────────────────────────────────────
        // STEP 1 — Short DB TX: atomic state guard (dedup layer 1).
        //         Do NOT hold this lock during any external provider call.
        // ────────────────────────────────────────────────────────────────
        DB::transaction(function () use ($payout, &$wonRace, &$blockedReason) {
            $locked = Payout::where('id', $payout->id)->lockForUpdate()->firstOrFail();

            $wonRace = false;
            $blockedReason = null;

            // Terminal dedup layer 3.
            if ($locked->status === Payout::STATUS_COMPLETED) {
                $payout->refresh();
                return;
            }

            if ($locked->status === Payout::STATUS_CANCELLED) {
                $blockedReason = 'cancelled';
                return;
            }

            if ($locked->status === Payout::STATUS_FAILED_FINAL) {
                $blockedReason = 'failed_final';
                return;
            }

            // Ambiguous: Safeguard 1 — NEVER auto-retry until reconcile clears it.
            if ($locked->status === Payout::STATUS_AMBIGUOUS) {
                $blockedReason = 'ambiguous';
                return;
            }

            if ($locked->status === Payout::STATUS_PROCESSING) {
                // Already in flight elsewhere. Treat as no-op; caller should wait.
                $payout->refresh();
                return;
            }

            // pending or failed → flip to queued atomically. The loser of a
            // concurrent race gets 0 affected rows → wonRace stays false.
            $increment = $locked->status === Payout::STATUS_FAILED ? 1 : 0;
            $wonRace = $locked->transitionIfStatusIn(
                [Payout::STATUS_PENDING, Payout::STATUS_FAILED],
                Payout::STATUS_QUEUED,
                $increment ? ['attempt_count' => DB::raw('attempt_count + 1')] : []
            );

            if (!$wonRace && !in_array($locked->status, [
                Payout::STATUS_QUEUED,
                Payout::STATUS_PROCESSING,
                Payout::STATUS_COMPLETED,
            ], true)) {
                $blockedReason = 'race_lost_status_' . $locked->status;
            }

            $payout->refresh();
        });

        if ($blockedReason === 'cancelled') {
            throw new LogicException('Cannot execute a cancelled payout.');
        }
        if ($blockedReason === 'failed_final') {
            throw new LogicException('Cannot execute a finally-failed payout.');
        }
        if ($blockedReason === 'ambiguous') {
            throw new LogicException(
                'Payout status is ambiguous due to a prior unknown gateway outcome. '
                . 'Reconcile via DisbursementProvider::queryStatus() before retrying.'
            );
        }
        if (!$wonRace && $payout->status !== Payout::STATUS_COMPLETED && $payout->status !== Payout::STATUS_PROCESSING) {
            // Concurrent caller already advanced state to queued or beyond;
            // treat as idempotent no-op and return current row.
            return $payout->fresh();
        }

        // Idempotent early return after guard if already done.
        if ($payout->status === Payout::STATUS_COMPLETED) {
            return $payout->fresh();
        }

        // ────────────────────────────────────────────────────────────────
        // STEP 2 — Resolve execution strategy.
        // ────────────────────────────────────────────────────────────────
        $strategy = $this->resolveStrategy($payout);

        // ────────────────────────────────────────────────────────────────
        // STEP 3 — Automated: flip queued → processing (short TX, no lock held long).
        // ────────────────────────────────────────────────────────────────
        if ($payout->execution_mode !== 'manual') {
            DB::transaction(function () use ($payout) {
                $locked = Payout::where('id', $payout->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== Payout::STATUS_QUEUED) {
                    // Somebody else already moved it; abort.
                    $payout->refresh();
                    return;
                }
                $locked->update([
                    'status' => Payout::STATUS_PROCESSING,
                ]);
                $payout->refresh();
            });

            if ($payout->status !== Payout::STATUS_PROCESSING) {
                return $payout->fresh();
            }
        }

        // ────────────────────────────────────────────────────────────────
        // STEP 4 — OUTSIDE ANY TX: invoke the strategy.
        //           For automated mode this is EXTERNAL HTTP.
        //           For manual mode it is in-process mark completed.
        //           Classify 3 buckets: success / retryable / non-retryable / ambiguous.
        // ────────────────────────────────────────────────────────────────
        try {
            $strategy->execute($payout, $payload);
        } catch (RetryableDisbursementException $e) {
            $payout->markAsFailed($e->getMessage());
            return $payout->fresh();
        } catch (NonRetryableDisbursementException $e) {
            $payout->markAsFailedFinal($e->getMessage());
            return $payout->fresh();
        } catch (AmbiguousDisbursementOutcomeException $e) {
            $payout->markAsAmbiguous(
                $e->getMessage(),
                $e->disbursementIdempotencyKey ?? $payout->disbursement_idempotency_key
            );
            return $payout->fresh();
        } catch (\Throwable $e) {
            // Unknown/unclassified exception → treat as ambiguous (Safeguard 1:
            // we simply do not know whether the provider side landed or not).
            $payout->markAsAmbiguous(
                'Unclassified strategy exception: ' . $e->getMessage(),
                $payout->disbursement_idempotency_key
            );
            return $payout->fresh();
        }

        // ────────────────────────────────────────────────────────────────
        // STEP 5 — Manual: strategy.markAsCompleted already flipped status → completed.
        //         Automated: strategy flips to processing (async) and
        //         DisbursementWebhookController will complete it later.
        // ────────────────────────────────────────────────────────────────
        return $payout->fresh();
    }

    public function cancelPayout(Payout $payout, ?User $cancelledBy = null, string $reason = ''): Payout
    {
        $flipped = $payout->transitionIfStatusIn(
            [Payout::STATUS_PENDING],
            Payout::STATUS_CANCELLED,
            [
                'last_error_message' => $reason ?: 'Cancelled' . ($cancelledBy ? " by {$cancelledBy->id}" : ''),
                'metadata' => array_merge($payout->metadata ?? [], [
                    'cancelled_by' => $cancelledBy?->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]),
            ]
        );

        if (!$flipped) {
            throw new LogicException(
                "Only pending payouts can be cancelled. Current status: {$payout->status}"
            );
        }

        return $payout->fresh();
    }

    protected function resolveStrategy(Payout $payout): ExecutionStrategy
    {
        if ($payout->execution_mode === 'manual') {
            return new ManualExecution();
        }

        return new AutomatedExecution();
    }
}
