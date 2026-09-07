<?php

namespace App\Domain\Commerce\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * ProductAttribute represents a custom attribute value for a product.
 * 
 * FLEXIBLE ATTRIBUTE SYSTEM:
 * Not all products have the same attributes. This system allows
 * the catalog to adapt to different product types without schema changes.
 * 
 * EXAMPLES:
 * - Hair products: hair type (dry/oily/normal), scent (unscented/mint)
 * - Tools: material (metal/plastic), weight, dimensions
 * - Services: duration, skill level required
 * 
 * ATTRIBUTE TYPES:
 * - text: Free-form text
 * - number: Numeric values
 * - select: Single choice from predefined options
 * - multiselect: Multiple choices
 * - boolean: Yes/No
 * - date: Date values
 * 
 * FILTERING:
 * The is_filterable flag enables category filtering
 * (e.g., "Show me all products for dry hair").
 * 
 * FUTURE-PROOFING:
 * This JSON-based attribute system allows new attribute types
 * to be added without database schema changes.
 */
class ProductAttribute extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'product_id',
        'attribute_name',
        'attribute_type',
        'value',
        'display_value',
        'sort_order',
        'is_filterable',
        'metadata',
    ];

    protected $casts = [
        'value' => 'array',
        'is_filterable' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Attribute types
     */
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_SELECT = 'select';
    const TYPE_MULTISELECT = 'multiselect';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';

    /**
     * Parent product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for filterable attributes
     */
    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    /**
     * Scope for specific attribute name
     */
    public function scopeWithName($query, string $name)
    {
        return $query->where('attribute_name', $name);
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
