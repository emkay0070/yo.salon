<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BrandExperience extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'experience_family',
        // 'logo' - DEPRECATED: Brand Experience does not own identity assets. Use Provider.logo instead.
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_heading',
        'font_body',
        'background_image',
        'custom_domain',
        'white_label_enabled',
    ];

    protected $casts = [
        'white_label_enabled' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
        'background_image_url',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'salon_id');
    }

    // logoMedia relationship removed - Brand Experience does not own identity assets

    public function backgroundImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'background_image');
    }

    public function getLogoUrlAttribute(): ?string
    {
        // Delegate to Provider.logo_url (canonical source of truth)
        // Brand Experience does not own identity assets
        return $this->provider?->logo_url;
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        if ($this->background_image && \Illuminate\Support\Str::isUuid($this->background_image)) {
            return $this->backgroundImageMedia?->url;
        }
        if ($this->background_image) {
            return asset('storage/' . $this->background_image);
        }
        return null;
    }
}
