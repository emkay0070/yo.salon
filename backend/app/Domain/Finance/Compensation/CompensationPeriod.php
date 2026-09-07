<?php

namespace App\Domain\Finance\Compensation;

use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * CompensationPeriod groups Earnings for a single payee over a defined date range.
 *
 * Status semantics:
 *   open     = still accumulating earnings and adjustments
 *   closing  = payable_amount locked, Settlement being created
 *   closed   = Settlement exists — obligation on record, money NOT yet paid
 *   settled  = all Payouts completed — money actually received
 *   voided   = cancelled (no earnings, staff terminated with nothing due)
 *
 * The gross_amount and adjustments_total are kept denormalized for performance.
 * They are recomputed whenever an Earning or Adjustment is added/removed.
 */
class CompensationPeriod extends Model
{
    use HasUuids;

    protected $fillable = [
        'compensatable_type',
        'compensatable_id',
        'provider_type',
        'provider_id',
        'compensation_policy_id',
        'period_start',
        'period_end',
        'gross_amount',
        'adjustments_total',
        'payable_amount',
        'currency',
        'status',
        'settlement_id',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'period_start'       => 'date',
        'period_end'         => 'date',
        'gross_amount'       => 'decimal:2',
        'adjustments_total'  => 'decimal:2',
        'payable_amount'     => 'decimal:2',
        'closed_at'          => 'datetime',
    ];

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_OPEN    = 'open';
    const STATUS_CLOSING = 'closing';
    const STATUS_CLOSED  = 'closed';
    const STATUS_SETTLED = 'settled';
    const STATUS_VOIDED  = 'voided';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function compensatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function provider(): MorphTo
    {
        return $this->morphTo();
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompensationPolicy::class, 'compensation_policy_id');
    }

    /** All Earnings included in this period */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /** Active (non-voided) earnings */
    public function activeEarnings(): HasMany
    {
        return $this->hasMany(Earning::class)->whereNotIn('status', [Earning::STATUS_VOIDED]);
    }

    /** Adjustments applied to this period */
    public function adjustments(): HasMany
    {
        return $this->hasMany(CompensationAdjustment::class);
    }

    /** The Settlement created when this period closes */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForCompensatable($query, string $type, string $id)
    {
        return $query->where('compensatable_type', $type)
                     ->where('compensatable_id', $id);
    }

    public function scopeForProvider($query, string $type, string $id)
    {
        return $query->where('provider_type', $type)
                     ->where('provider_id', $id);
    }

    /** Periods that have passed their end date and are still open */
    public function scopeDueForClosing($query)
    {
        return $query->where('status', self::STATUS_OPEN)
                     ->where('period_end', '<', now()->toDateString());
    }

    // ── Financials ────────────────────────────────────────────────────────────

    /**
     * Recalculate gross_amount from all active Earnings and recompute payable_amount.
     * Call this whenever an Earning is added, removed, or voided.
     */
    public function recalculate(): void
    {
        $this->gross_amount = $this->activeEarnings()->sum('gross_amount');
        $this->payable_amount = (float) $this->gross_amount + (float) $this->adjustments_total;
        $this->save();
    }

    /**
     * Add an adjustment and recompute payable_amount.
     */
    public function applyAdjustment(CompensationAdjustment $adjustment): void
    {
        $this->adjustments_total = (float) $this->adjustments_total + (float) $adjustment->amount;
        $this->payable_amount = (float) $this->gross_amount + (float) $this->adjustments_total;
        $this->save();
    }

    // ── State transitions ─────────────────────────────────────────────────────

    /**
     * Begin the closing process.
     * Locks the payable_amount. Called before Settlement creation.
     */
    public function beginClosing(): void
    {
        if ($this->status !== self::STATUS_OPEN) {
            throw new \LogicException("Cannot close period: status is '{$this->status}'.");
        }

        $this->recalculate();
        $this->status = self::STATUS_CLOSING;
        $this->save();
    }

    /**
     * Finalize — Settlement has been created.
     * Period is now 'closed': obligation exists, money not yet paid.
     */
    public function markAsClosed(int $settlementId, ?int $closedBy = null): void
    {
        if ($this->status !== self::STATUS_CLOSING) {
            throw new \LogicException("Cannot mark period closed: status is '{$this->status}'.");
        }

        $this->settlement_id = $settlementId;
        $this->closed_by = $closedBy;
        $this->closed_at = now();
        $this->status = self::STATUS_CLOSED;
        $this->save();
    }

    /**
     * Mark as settled — all Payouts for this period's Settlement are complete.
     * Called by Payout completion webhook / event.
     */
    public function markAsSettled(): void
    {
        if ($this->status !== self::STATUS_CLOSED) {
            throw new \LogicException("Cannot mark period settled: status is '{$this->status}'.");
        }

        $this->status = self::STATUS_SETTLED;
        $this->save();
    }

    /**
     * Void this period — no earnings, nothing due.
     */
    public function void(): void
    {
        if ($this->status !== self::STATUS_OPEN) {
            throw new \LogicException("Can only void an open period.");
        }

        $this->status = self::STATUS_VOIDED;
        $this->save();
    }
}
