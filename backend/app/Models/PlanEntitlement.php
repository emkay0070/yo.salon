<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlanEntitlement extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_id',
        'resource_code',
        'limit',
        'reset_period',
        'is_active',
    ];

    protected $casts = [
        'limit'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BillingResource::class, 'resource_code', 'code');
    }

    /**
     * Whether this entitlement is unlimited.
     * Convention: -1 means unlimited.
     */
    public function isUnlimited(): bool
    {
        return $this->limit === -1;
    }

    /**
     * Whether a resource is included in this plan at all.
     */
    public function isIncluded(): bool
    {
        return $this->limit !== 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForResource($query, string $resourceCode)
    {
        return $query->where('resource_code', $resourceCode);
    }
}
