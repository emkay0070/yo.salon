<?php

namespace App\Domain\Finance\Payout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Payout represents an actual money transfer.
 * 
 * Payouts are the execution of settlements. When a salon pays a specialist,
 * that payment is recorded as a Payout.
 * 
 * Payouts can be:
 * - Manual (cash, bank transfer recorded by salon)
 * - Automatic (via payment provider API - future)
 * 
 * The Finance Domain tracks payouts but doesn't execute them.
 * Execution is handled by the Payout Domain.
 */
class Payout extends Model
{
    use HasUuids;

    const STATUS_PENDING = 'pending';
    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_FAILED_FINAL = 'failed_final';
    const STATUS_AMBIGUOUS = 'ambiguous';
    const STATUS_CANCELLED = 'cancelled';

    const MAX_RETRY_ATTEMPTS = 3;

    protected $fillable = [
        'idempotency_key',
        'disbursement_idempotency_key',
        'settlement_id',
        'recipient_type',
        'recipient_id',
        'amount',
        'currency',
        'method',
        'execution_mode',
        'status',
        'attempt_count',
        'last_error_at',
        'last_error_message',
        'reference',
        'provider',
        'provider_reference',
        'processed_by',
        'initiated_at',
        'completed_at',
        'failure_reason',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'attempt_count' => 'integer',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_error_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The settlement this payout is for
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Finance\Settlement\Settlement::class);
    }

    /**
     * The entity receiving the payout
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who processed this payout (for manual payouts)
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'processed_by');
    }

    /**
     * Mark payout as completed
     */
    public function markAsCompleted(?string $providerReference = null): void
    {
        if ($this->status === 'completed') {
            return; // terminal state, safe no-op
        }

        $this->update([
            'status' => 'completed',
            'provider_reference' => $providerReference,
            'completed_at' => now(),
        ]);

        // Update settlement if applicable
        if ($this->settlement) {
            $this->settlement->refresh();
            
            $completedAmount = $this->settlement->payouts()
                ->where('status', 'completed')
                ->sum('amount');
                
            if ($completedAmount >= $this->settlement->amount) {
                $this->settlement->markAsPaid($this->completed_at);
            } else if ($completedAmount > 0) {
                $this->settlement->markAsPartiallyPaid();
            }
        }
    }

    /**
     * Mark payout as failed (recoverable — eligible for retry).
     */
    public function markAsFailed(string $reason): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'last_error_at' => now(),
            'last_error_message' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'failed_at' => now(),
            ]),
        ]);
    }

    /**
     * Mark payout as failed_final (non-recoverable — never retry).
     */
    public function markAsFailedFinal(string $reason): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_FAILED_FINAL,
            'failure_reason' => $reason,
            'last_error_at' => now(),
            'last_error_message' => $reason,
        ]);
    }

    /**
     * Mark payout as ambiguous (provider outcome unknown — block auto-retry).
     * Reconciliation job must query provider status via disbursement_idempotency_key.
     */
    public function markAsAmbiguous(string $reason, ?string $disbursementIdempotencyKey = null): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_AMBIGUOUS,
            'disbursement_idempotency_key' => $disbursementIdempotencyKey ?? $this->disbursement_idempotency_key,
            'last_error_at' => now(),
            'last_error_message' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'ambiguous_at' => now(),
                'ambiguous_reason' => $reason,
            ]),
        ]);
    }

    /**
     * Atomically flip status from a known source set to a target.
     * Returns true if this process won the race and actually flipped.
     *
     * @param  string[]  $fromStatuses
     */
    public function transitionIfStatusIn(array $fromStatuses, string $toStatus, array $additionalUpdates = []): bool
    {
        $affected = self::where('id', $this->id)
            ->whereIn('status', $fromStatuses)
            ->update(array_merge($additionalUpdates, [
                'status' => $toStatus,
            ]));

        if ($affected > 0) {
            $this->refresh();
            return true;
        }

        $this->refresh();
        return false;
    }

    /**
     * True if the payout is in a terminal state and no further state changes should occur
     * without reconciliation intervention.
     */
    public function isTerminalForAutoExecute(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED_FINAL,
            self::STATUS_AMBIGUOUS,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Scope for pending payouts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed payouts
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed payouts
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for specific recipient
     */
    public function scopeForRecipient($query, string $recipientType, string $recipientId)
    {
        return $query->where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId);
    }

    /**
     * Scope for specific method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }
}
