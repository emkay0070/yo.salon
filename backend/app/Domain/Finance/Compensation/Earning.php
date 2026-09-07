<?php

namespace App\Domain\Finance\Compensation;

use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Earning represents an atomic earning event for a compensatable entity.
 *
 * Source types:
 *   App\Models\Booking             → commission, per_booking, hybrid-commission
 *   CompensationPeriod             → salary, fixed, hybrid-base
 *
 * Status lifecycle:
 *   earned → included → payable → paid
 *   earned → payable (immediate) → paid
 *   earned | included → voided
 *
 * IMPORTANT: status = 'paid' means a Payout was COMPLETED.
 * status = 'payable' means a Settlement was created — obligation exists but not yet paid.
 */
class Earning extends Model
{
    use HasUuids;

    protected $fillable = [
        'compensatable_type',
        'compensatable_id',
        'source_type',
        'source_id',
        'compensation_policy_id',
        'compensation_period_id',
        'settlement_id',
        'gross_amount',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'metadata'     => 'array',
    ];

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_EARNED   = 'earned';
    const STATUS_INCLUDED = 'included';
    const STATUS_PAYABLE  = 'payable';
    const STATUS_PAID     = 'paid';
    const STATUS_VOIDED   = 'voided';

    // ── Relationships ─────────────────────────────────────────────────────────

    /** The entity that earned this amount */
    public function compensatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The economic event that caused this earning.
     * Either a Booking (commission/per_booking) or a CompensationPeriod (salary/base).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Policy snapshot — tells us exactly what rules applied when this was calculated */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompensationPolicy::class, 'compensation_policy_id');
    }

    /** The period this earning belongs to (null for immediate schedule) */
    public function period(): BelongsTo
    {
        return $this->belongsTo(CompensationPeriod::class, 'compensation_period_id');
    }

    /**
     * The Settlement created for this earning.
     * Populated for 'immediate' schedule only.
     * For period-based earnings, the Settlement belongs to the CompensationPeriod.
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForCompensatable($query, string $type, string $id)
    {
        return $query->where('compensatable_type', $type)
                     ->where('compensatable_id', $id);
    }

    public function scopeEarned($query)
    {
        return $query->where('status', self::STATUS_EARNED);
    }

    public function scopeIncluded($query)
    {
        return $query->where('status', self::STATUS_INCLUDED);
    }

    public function scopePayable($query)
    {
        return $query->where('status', self::STATUS_PAYABLE);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeVoided($query)
    {
        return $query->where('status', self::STATUS_VOIDED);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_VOIDED]);
    }

    // ── State transitions ─────────────────────────────────────────────────────

    /**
     * Include this earning in a CompensationPeriod.
     * Only valid from status = 'earned'.
     */
    public function includeInPeriod(CompensationPeriod $period): void
    {
        if ($this->status !== self::STATUS_EARNED) {
            throw new \LogicException("Cannot include earning in period: current status is '{$this->status}'.");
        }

        $this->compensation_period_id = $period->id;
        $this->status = self::STATUS_INCLUDED;
        $this->save();
    }

    /**
     * Mark as payable — a Settlement now exists.
     * Valid from 'earned' (immediate) or 'included' (period closed).
     */
    public function markAsPayable(?int $settlementId = null): void
    {
        if (!in_array($this->status, [self::STATUS_EARNED, self::STATUS_INCLUDED])) {
            throw new \LogicException("Cannot mark earning payable: current status is '{$this->status}'.");
        }

        if ($settlementId) {
            $this->settlement_id = $settlementId;
        }

        $this->status = self::STATUS_PAYABLE;
        $this->save();
    }

    /**
     * Mark as paid — a Payout has been completed.
     * This is the ONLY way status reaches 'paid'.
     */
    public function markAsPaid(): void
    {
        if ($this->status !== self::STATUS_PAYABLE) {
            throw new \LogicException("Cannot mark earning paid: current status is '{$this->status}'.");
        }

        $this->status = self::STATUS_PAID;
        $this->save();
    }

    /**
     * Void this earning (booking cancelled, policy error, etc.)
     * Only valid from 'earned' or 'included'.
     */
    public function void(): void
    {
        if (!in_array($this->status, [self::STATUS_EARNED, self::STATUS_INCLUDED])) {
            throw new \LogicException("Cannot void earning: current status is '{$this->status}'. Use a CompensationAdjustment for paid/payable earnings.");
        }

        $this->status = self::STATUS_VOIDED;
        $this->save();
    }
}
