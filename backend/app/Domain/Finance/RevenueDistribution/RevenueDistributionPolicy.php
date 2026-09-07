<?php

namespace App\Domain\Finance\RevenueDistribution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * RevenueDistributionPolicy defines how revenue should be distributed.
 * 
 * Policies are stored in the database (no hardcoding) and define:
 * - Which parties receive what percentage/fixed amount
 * - Context-specific rules (seniority, time, promotion, etc.)
 * - Applicable to any revenue type (bookings, products, training, etc.)
 * 
 * The engine executes policies. The database decides the rules.
 */
class RevenueDistributionPolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'context_type',
        'context_id',
        'revenue_type',
        'name',
        'description',
        'is_active',
        'priority',
        'effective_from',
        'effective_until',
        'rules',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'rules' => 'array',
        'metadata' => 'array',
    ];

    /**
     * The context this policy applies to (Salon, Assignment, Service, etc.)
     */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for active policies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>', now());
            });
    }

    /**
     * Scope for specific context
     */
    public function scopeForContext($query, string $contextType, string $contextId)
    {
        return $query->where('context_type', $contextType)
            ->where('context_id', $contextId);
    }

    /**
     * Scope for specific revenue type
     */
    public function scopeForRevenueType($query, string $revenueType)
    {
        return $query->where('revenue_type', $revenueType);
    }

    /**
     * Scope for highest priority first
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Get applicable policy for a context and revenue type
     */
    public static function getApplicablePolicy(string $contextType, string $contextId, string $revenueType): ?self
    {
        return self::forContext($contextType, $contextId)
            ->forRevenueType($revenueType)
            ->active()
            ->byPriority()
            ->first();
    }

    /**
     * Get default policy for revenue type (fallback)
     */
    public static function getDefaultPolicy(string $revenueType): ?self
    {
        return self::whereNull('context_type')
            ->whereNull('context_id')
            ->forRevenueType($revenueType)
            ->active()
            ->byPriority()
            ->first();
    }
}
