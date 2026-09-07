<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CraftTaxonomy extends Model
{
    use HasUuids;

    protected $table = 'craft_taxonomy';

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

    protected $appends = [
        'image_url',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CraftTaxonomy::class, 'parent_id');
    }

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image && \Illuminate\Support\Str::isUuid($this->image)) {
            return $this->imageMedia?->url;
        }
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    public function children(): HasMany
    {
        return $this->hasMany(CraftTaxonomy::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
