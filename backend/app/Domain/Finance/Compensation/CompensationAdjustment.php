<?php

namespace App\Domain\Finance\Compensation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * CompensationAdjustment applies a signed amount to a CompensationPeriod.
 *
 * Types and sign convention:
 *   bonus     → positive
 *   tip       → positive
 *   allowance → positive
 *   deduction → negative
 *   penalty   → negative
 *
 * The `amount` column itself is signed (negative for deductions/penalties).
 * This makes summing all adjustments for a period straightforward.
 */
class CompensationAdjustment extends Model
{
    use HasUuids;

    protected $fillable = [
        'compensatable_type',
        'compensatable_id',
        'compensation_period_id',
        'type',
        'amount',
        'currency',
        'description',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ── Type constants ────────────────────────────────────────────────────────

    const TYPE_BONUS     = 'bonus';
    const TYPE_TIP       = 'tip';
    const TYPE_ALLOWANCE = 'allowance';
    const TYPE_DEDUCTION = 'deduction';
    const TYPE_PENALTY   = 'penalty';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function compensatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(CompensationPeriod::class, 'compensation_period_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPositive(): bool
    {
        return (float) $this->amount > 0;
    }

    public function isDeduction(): bool
    {
        return (float) $this->amount < 0;
    }
}
