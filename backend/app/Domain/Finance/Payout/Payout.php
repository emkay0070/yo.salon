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

    protected $fillable = [
        'idempotency_key',
        'settlement_id',
        'recipient_type',
        'recipient_id',
        'amount',
        'currency',
        'method',
        'execution_mode',
        'status',
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
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
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
     * Mark payout as failed
     */
    public function markAsFailed(string $reason): void
    {
        if ($this->status === 'completed') {
            return; // terminal state, safe no-op
        }

        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'failed_at' => now(),
            ]),
        ]);
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
