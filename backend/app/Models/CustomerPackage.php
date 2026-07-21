<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerPackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'salon_id',
        'package_id',
        'services_remaining',
        'expires_at',
    ];

    protected $casts = [
        'services_remaining' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    /**
     * Check if package is active
     */
    public function isActive(): bool
    {
        return $this->services_remaining > 0 
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
