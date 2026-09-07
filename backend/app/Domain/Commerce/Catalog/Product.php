<?php

namespace App\Domain\Commerce\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Product represents a product in the catalog.
 * 
 * Products have polymorphic ownership:
 * - Platform (global catalog)
 * - Supplier (supplier products)
 * - Salon (private formulas)
 * 
 * Products know details but never know stock levels (that's Inventory domain).
 */
class Product extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'brand_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'short_description',
        'manufacturer',
        'status',
        'visibility',
        'is_trackable',
        'is_service_product',
        'requires_batch_tracking',
        'requires_expiry_tracking',
        'metadata',
    ];

    protected $casts = [
        'is_trackable' => 'boolean',
        'is_service_product' => 'boolean',
        'requires_batch_tracking' => 'boolean',
        'requires_expiry_tracking' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Product statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DISCONTINUED = 'discontinued';

    /**
     * Product visibility
     */
    const VISIBILITY_PUBLIC = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_HIDDEN = 'hidden';

    /**
     * The entity that owns this product
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Brand of this product
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Category of this product
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Variants (SKUs) of this product
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Images of this product
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Attributes of this product
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for public visibility
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /**
     * Scope for specific owner
     */
    public function scopeOwnedBy($query, string $ownerType, string $ownerId)
    {
        return $query->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId);
    }

    /**
     * Scope for specific brand
     */
    public function scopeOfBrand($query, string $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope for specific category
     */
    public function scopeInCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope for trackable products
     */
    public function scopeTrackable($query)
    {
        return $query->where('is_trackable', true);
    }

    /**
     * Check if product is owned by platform
     * 
     * Platform-owned products are in the global catalog
     * and available to all salons by default.
     * 
     * @return bool
     */
    public function isPlatformOwned(): bool
    {
        return $this->owner_type === \App\Models\Platform::class;
    }

    /**
     * Check if product is owned by supplier
     * 
     * Supplier-owned products are uploaded by suppliers
     * and can be browsed/ordered by salons.
     * 
     * @return bool
     */
    public function isSupplierOwned(): bool
    {
        return $this->owner_type === \App\Models\Provider::class 
            && $this->owner?->type === \App\Models\Provider::TYPE_SUPPLIER;
    }

    /**
     * Check if product is owned by salon
     * 
     * Salon-owned products are private formulas or custom products
     * specific to that salon.
     * 
     * @return bool
     */
    public function isSalonOwned(): bool
    {
        return $this->owner_type === \App\Models\Salon::class;
    }
}
