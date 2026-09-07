<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SalonCapacity extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
        'resource_code',
        'additional_capacity',
        'expires_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'additional_capacity' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BillingResource::class, 'resource_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForResource($query, string $resourceCode)
    {
        return $query->where('resource_code', $resourceCode);
    }

    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    /**
     * Check if this capacity record is currently valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
