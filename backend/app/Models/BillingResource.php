<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BillingResource extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'unit_label',
        'is_metered',
        'is_active',
        'type',
    ];

    protected $casts = [
        'is_metered' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(PlatformProduct::class, 'resource_code', 'code');
    }

    public function planEntitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class, 'resource_code', 'code');
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class, 'resource_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMetered($query)
    {
        return $query->where('is_metered', true);
    }
}
