<?php

namespace App\Domain\Commerce\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Brand represents a product brand.
 * 
 * CRITICAL DISTINCTION: A brand is NOT a supplier.
 * 
 * Example:
 * - Brand: American Crew (the product brand)
 * - Supplier: Beauty Wholesale Uganda (the distributor)
 * 
 * WHY SEPARATE?
 * - Multiple suppliers can distribute the same brand
 * - A supplier can distribute multiple brands
 * - Brand-specific analytics and marketing
 * - Brand-specific customer preferences
 * 
 * This separation enables:
 * - Multi-supplier distribution
 * - Brand-based product recommendations
 * - Supplier-independent brand management
 */
class Brand extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'website',
        'description',
        'country',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Products belonging to this brand
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope for active brands
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific country
     */
    public function scopeFromCountry($query, string $country)
    {
        return $query->where('country', $country);
    }
}
