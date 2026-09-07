<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlatformProduct extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'resource_code',
        'type',
        'billing_model',
        'units',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'units'     => 'integer',
        'is_active' => 'boolean',
        'metadata'  => 'array',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'product_id');
    }

    public function resource(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BillingResource::class, 'resource_code', 'code');
    }

    /**
     * Get the price for a given price book code.
     */
    public function priceFor(string $priceBookCode = 'uganda_default'): ?Price
    {
        return $this->prices()
            ->whereHas('priceBook', fn ($q) => $q->where('code', $priceBookCode))
            ->where('is_active', true)
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
