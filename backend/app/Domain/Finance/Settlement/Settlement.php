<?php

namespace App\Domain\Finance\Settlement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * Settlement represents who owes whom.
 * 
 * A settlement is created when revenue is distributed:
 * - Salon owes Specialist commission
 * - Salon owes Supplier for products
 * - Platform owes Salon for refunds
 * 
 * The actual payment (payout) happens separately. Settlements track
 * the obligation, not the money movement.
 */
class Settlement extends Model
{
    // ── Status constants ────────────────────────────────────────────
    // Must match the values stored in and queried from the database.
    // Never reference raw strings elsewhere — always use these constants.
    public const STATUS_PENDING          = 'pending';
    public const STATUS_PARTIALLY_PAID   = 'partially_paid';
    public const STATUS_PAID             = 'paid';
    public const STATUS_OVERDUE          = 'overdue';
    public const STATUS_DISPUTED         = 'disputed';
    public const STATUS_CANCELLED        = 'cancelled';



    protected $fillable = [
        'payable_type',
        'payable_id',
        'recipient_type',
        'recipient_id',
        'amount',
        'currency',
        'status',
        'reference_type',
        'reference_id',
        'scheduled_for',
        'completed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'scheduled_for' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The entity that owes the money (Salon, Platform, etc.)
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entity that should receive the money (Specialist, Supplier, etc.)
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entity that caused this settlement (Booking, Invoice, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Payouts that have been made for this settlement
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(\App\Domain\Finance\Payout\Payout::class);
    }

    /**
     * Mark settlement as paid
     */
    /**
     * Mark settlement as paid.
     *
     * @param \DateTimeInterface|string|null $paidAt When the payment was processed.
     *        Defaults to now(). Payout::markAsCompleted passes its own processed_at.
     */
    public function markAsPaid($paidAt = null): void
    {
        $this->update([
            'status'  => self::STATUS_PAID,
            'completed_at' => $paidAt ?? now(),
        ]);

        event(new \App\Domain\Finance\Events\SettlementClosed(
            settlementId: $this->id,
            payableType: $this->payable_type,
            payableId: $this->payable_id,
            recipientType: $this->recipient_type,
            recipientId: $this->recipient_id,
            amount: $this->amount,
            currency: $this->currency,
            metadata: $this->metadata ?? []
        ));
    }

    /**
     * Mark settlement as partially paid
     */
    public function markAsPartiallyPaid(): void
    {
        $this->update(['status' => self::STATUS_PARTIALLY_PAID]);
    }

    /**
     * Cancel settlement
     */
    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Mark settlement as disputed
     */
    public function markAsDisputed(): void
    {
        $this->update(['status' => self::STATUS_DISPUTED]);
    }

    /**
     * Calculate remaining amount to be paid
     */
    public function getRemainingAmountAttribute(): float
    {
        // Failed and cancelled payouts do not count towards the paid amount.
        // Pending, processing, and retrying payouts DO count here to prevent over-initiating new payouts while one is active.
        $paidOrPendingAmount = $this->payouts()
            ->whereIn('status', ['completed', 'pending', 'processing', 'retrying'])
            ->sum('amount');
            
        return max(0, $this->amount - $paidOrPendingAmount);
    }

    /**
     * Scope for pending settlements
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for paid settlements
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for overdue settlements
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_for', '<', now());
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
     * Scope for specific payable
     */
    public function scopeForPayable($query, string $payableType, string $payableId)
    {
        return $query->where('payable_type', $payableType)
            ->where('payable_id', $payableId);
    }
}
