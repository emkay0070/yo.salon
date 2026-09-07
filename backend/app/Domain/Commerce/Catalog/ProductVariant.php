<?php

namespace App\Domain\Commerce\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * ProductVariant represents a SKU (Stock Keeping Unit) - a specific variant of a product.
 * 
 * VARIANT CONCEPT:
 * One product can have multiple variants based on attributes like size, color, etc.
 * Each variant has its own SKU, price, and inventory tracking.
 * 
 * Example:
 * Product: American Crew Fiber
 * ├── SKU: AC-FIBER-85G (85g) - Price: 25,000 UGX
 * ├── SKU: AC-FIBER-100G (100g) - Price: 30,000 UGX
 * └── SKU: AC-FIBER-150G (150g) - Price: 40,000 UGX
 * 
 * WHY VARIANTS?
 * - Different sizes/prices of the same product
 * - Different colors of the same product
 * - Different packaging options
 * - Inventory tracking at SKU level
 * 
 * INTEGRATION:
 * - Inventory domain tracks quantities at SKU level
 * - Catalog domain provides SKU details
 * - Pricing can vary by variant
 */
class ProductVariant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku_code',
        'barcode',
        'name',
        'variant_attributes',
        'price',
        'cost',
        'compare_at_price',
        'weight',
        'weight_unit',
        'status',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'weight' => 'decimal:4',
        'variant_attributes' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Variant statuses
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_OUT_OF_STOCK = 'out_of_stock';

    /**
     * Parent product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Images for this variant
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Scope for active variants
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for in stock
     */
    public function scopeInStock($query)
    {
        return $query->where('status', '!=', self::STATUS_OUT_OF_STOCK);
    }

    /**
     * Scope for specific SKU
     */
    public function scopeWithSku($query, string $sku)
    {
        return $query->where('sku_code', $sku);
    }

    /**
     * Scope for specific barcode
     */
    public function scopeWithBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }
}
