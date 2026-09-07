<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'renews_at',
        'cancelled_at',
        'cancel_reason',
        'metadata',
        'is_over_limit',
        'is_grandfathered',
        'grandfathering_metadata',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'renews_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'is_over_limit' => 'boolean',
        'is_grandfathered' => 'boolean',
        'grandfathering_metadata' => 'array',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function cancelled(): bool
    {
        return !is_null($this->cancelled_at);
    }
}
