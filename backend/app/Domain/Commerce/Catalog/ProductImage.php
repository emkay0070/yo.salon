<?php

namespace App\Domain\Commerce\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * ProductImage represents an image for a product or variant.
 */
class ProductImage extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'url',
        'alt_text',
        'sort_order',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * The entity this image belongs to (Product or ProductVariant)
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for primary images
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
