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
        'logo',
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

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo');
    }

    public function backgroundImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'background_image');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo && \Illuminate\Support\Str::isUuid($this->logo)) {
            return $this->logoMedia?->url;
        }
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }
        return null;
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
