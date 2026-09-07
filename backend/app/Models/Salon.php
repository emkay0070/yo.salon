<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Salon extends Model
{
    use HasUuids, HasFactory;
    protected $fillable = [
        'provider_id',
        'name',
        'slug',
        'type',
        'description',
        'logo',
        'logo_url',
        'cover_image',
        'cover_image_url',
        'whatsapp',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'website',
        'lat',
        'lng',
        'rating',
        'review_count',
        'is_open',
        'accepting_online_bookings',
        'parking_info',
        'booking_deposit_enabled',
        'deposit_type',
        'deposit_value',
        'deposit_required_for',
        'deposit_min_service_amount',
        'schedule_mode',
        'category',
        'vibe',
        'business_type',
        'team_size',
        'branches',
        'timezone',
        'currency',
        'opening_hours',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    protected $appends = [
        'logo_url',
        'cover_image_url',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Services that could potentially be offered by this branch (via its Provider).
     * Note: This does NOT apply branch-level availability or pricing overrides.
     * Use BranchServiceCatalogResolver to get the actual branch catalog.
     */
    public function services()
    {
        return $this->hasManyThrough(Service::class, Provider::class, 'id', 'provider_id', 'provider_id', 'id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * Specialists assigned to this salon via SpecialistAssignment
     */
    public function specialists()
    {
        return $this->hasManyThrough(
            Specialist::class,
            SpecialistAssignment::class,
            'salon_id',
            'id',
            'id',
            'specialist_id'
        );
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SalonSchedule::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function subscription()
    {
        return $this->hasOneThrough(Subscription::class, Provider::class, 'id', 'provider_id', 'provider_id', 'id');
    }

    public function bookingConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SalonBookingConfig::class);
    }

    public function brandExperience()
    {
        return $this->hasOne(BrandExperience::class);
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo');
    }

    public function coverImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_image');
    }

    public function getLogoUrlAttribute(): ?string
    {
        // If logo is a UUID, get from media relationship
        if ($this->logo && \Illuminate\Support\Str::isUuid($this->logo)) {
            return $this->logoMedia?->url;
        }
        // If logo is a path (backward compatibility), use asset
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }
        return null;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        // If cover_image is a UUID, get from media relationship
        if ($this->cover_image && \Illuminate\Support\Str::isUuid($this->cover_image)) {
            return $this->coverImageMedia?->url;
        }
        // If cover_image is a path (backward compatibility), use asset
        if ($this->cover_image) {
            return asset('storage/' . $this->cover_image);
        }
        return null;
    }
}
