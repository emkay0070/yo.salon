<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UsageEvent extends Model
{
    use HasUuids;

    /**
     * This table is IMMUTABLE. No updates or deletes are permitted.
     * It is an append-only audit log of every resource consumption event.
     */

    protected $fillable = [
        'provider_id',
        'subscription_id',
        'resource_code',
        'units',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'units'       => 'integer',
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BillingResource::class, 'resource_code', 'code');
    }

    // ─────────────────────────────────────────────
    // Immutability guards — never update or delete
    // ─────────────────────────────────────────────

    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('UsageEvent records are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('UsageEvent records are immutable and cannot be deleted.');
        });
    }

    // ─────────────────────────────────────────────
    // Factory helper
    // ─────────────────────────────────────────────

    /**
     * Record a single usage event.
     */
    public static function record(
        string $providerId,
        string $resourceCode,
        int $units = 1,
        array $metadata = [],
        ?string $subscriptionId = null
    ): self {
        return static::create([
            'provider_id'     => $providerId,
            'subscription_id' => $subscriptionId,
            'resource_code'   => $resourceCode,
            'units'           => $units,
            'metadata'        => $metadata,
            'occurred_at'     => now(),
        ]);
    }

    // ─────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────

    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForResource($query, string $resourceCode)
    {
        return $query->where('resource_code', $resourceCode);
    }

    public function scopeInPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('occurred_at', [$from, $to]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('occurred_at', [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }
}
