<?php

namespace App\Domain\Finance\Compensation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * CompensationPolicy defines what a salon has agreed to pay a staff member.
 *
 * The salon/manager creates and owns all policies. Yo.Salon executes them
 * but never decides the terms. Policies are time-bounded to preserve history.
 *
 * For specialists: scoped to specialist_assignments (different rates per salon).
 * For non-specialists: scoped to (compensatable, provider) directly.
 */
class CompensationPolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'compensatable_type',
        'compensatable_id',
        'provider_type',
        'provider_id',
        'assignment_id',
        'type',
        'rules',
        'settlement_frequency',
        'auto_create_earnings',
        'auto_close_periods',
        'manager_approval_required',
        'effective_from',
        'effective_until',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'rules'                     => 'array',
        'effective_from'            => 'date',
        'effective_until'           => 'date',
        'is_active'                 => 'boolean',
        'auto_create_earnings'      => 'boolean',
        'auto_close_periods'        => 'boolean',
        'manager_approval_required' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    /** The entity being compensated (Specialist, User, etc.) */
    public function compensatable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The salon / provider that owns this policy */
    public function provider(): MorphTo
    {
        return $this->morphTo();
    }

    /** Earnings produced under this policy */
    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /** Compensation periods opened under this policy */
    public function periods(): HasMany
    {
        return $this->hasMany(CompensationPeriod::class);
    }

    /** Manager who created these terms */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', now()->toDateString());
            });
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

    public function scopeForAssignment($query, string $assignmentId)
    {
        return $query->where('assignment_id', $assignmentId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** True if this policy creates Settlements immediately on each Earning */
    public function isImmediate(): bool
    {
        return $this->settlement_frequency === 'immediate';
    }

    /** True if earnings accumulate into a CompensationPeriod */
    public function isPeriodBased(): bool
    {
        return !$this->isImmediate();
    }

    /** True if this policy pays a salary (earning sourced from period, not booking) */
    public function isSalaryBased(): bool
    {
        return in_array($this->type, ['salary', 'fixed']);
    }

    /**
     * Find the rate / amount applicable for a given service category.
     * Falls back to catch-all '*' if no specific category rule matches.
     */
    public function ruleForCategory(?string $category): ?array
    {
        $rules = $this->rules ?? [];

        // Look for an exact category match first
        foreach ($rules as $rule) {
            if (($rule['service_category'] ?? null) === $category) {
                return $rule;
            }
        }

        // Fallback: catch-all
        foreach ($rules as $rule) {
            if (($rule['service_category'] ?? null) === '*') {
                return $rule;
            }
        }

        // For salary/fixed: just return the first rule
        return $rules[0] ?? null;
    }
}
