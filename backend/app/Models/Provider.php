<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'status',
        'display_name',
        'slug',
        'description',
        'story',
        'mission',
        'logo',
        'cover_image',
        'phone',
        'email',
        'website',
        'location',
        'social_links',
        'settings',
        'timezone',
        'availability_settings',
        'rating',
        'review_count',
        'years_in_business',
        'awards',
        'gallery',
        'active',
    ];

    /**
     * Provider types
     */
    const TYPE_SALON = 'salon';
    const TYPE_SPECIALIST = 'independent_specialist';
    const TYPE_SUPPLIER = 'supplier';

    protected $casts = [
        'location' => 'array',
        'social_links' => 'array',
        'settings' => 'array',
        'rating' => 'decimal:2',
        'active' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
        'cover_image_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logoMedia?->url;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->coverImageMedia?->url;
    }

    // Relationships
    public function salon(): HasOne
    {
        return $this->hasOne(Salon::class);
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo');
    }

    public function coverImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_image');
    }

    public function specialists(): HasMany
    {
        return $this->hasMany(Specialist::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function bookingConfig(): HasOne
    {
        return $this->hasOne(ProviderBookingConfig::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
