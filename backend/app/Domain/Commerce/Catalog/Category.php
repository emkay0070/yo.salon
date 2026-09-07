<?php

namespace App\Domain\Commerce\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Category represents a product category (hierarchical).
 * 
 * HIERARCHICAL STRUCTURE:
 * Categories support parent-child relationships for organizing products.
 * Example: Hair Care → Shampoo → Dry Hair
 * 
 * This hierarchy enables:
 * - Organized product browsing
 * - Category-based filtering
 * - Breadcrumb navigation
 * - Category-specific analytics
 * 
 * FULL PATH:
 * The getFullPathAttribute() method returns the complete category path
 * (e.g., "Hair Care > Shampoo > Dry Hair") for display and navigation.
 */
class Category extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Products in this category
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope for root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific parent
     */
    public function scopeChildrenOf($query, string $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Get full category path (e.g., "Hair Care > Shampoo > Dry Hair")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }
}
