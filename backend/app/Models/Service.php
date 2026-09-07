<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Media;

class Service extends Model
{
    use HasUuids, HasFactory;
    
    protected $fillable = [
        'provider_id',
        'name',
        'description',
        'price',
        'duration',
        'category',
        'craft_taxonomy_id',
        'image_path',
        'image_media_id',
        'active',
    ];

    protected $casts = [
        'price' => 'float',
        'active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_media_id) {
            return $this->imageMedia?->url;
        }
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        return null;
    }

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function craftTaxonomy(): BelongsTo
    {
        return $this->belongsTo(CraftTaxonomy::class);
    }
}
