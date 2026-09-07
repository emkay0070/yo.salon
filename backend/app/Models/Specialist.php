<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Specialist extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'provider_id',
        'name',
        'phone',
        'email',
        'handle',
        'headline',
        'role',
        'bio',
        'specialties',
        'languages',
        'qualifications',
        'portfolio',
        'photo',
        'photo_media_id',
        'social_links',
        'profile_image',
        'skills',
        'years_experience',
        'certifications',
        'achievements',
        'career_timeline',
        'rating',
        'review_count',
        'follower_count',
        'favorite_count',
        'active',
        'verification_status',
        'verified_at',
        'punctuality',
        'attendance',
        'response_rate',
        'latitude',
        'longitude',
        'work_preference',
    ];

    protected $casts = [
        'specialties' => 'array',
        'languages' => 'array',
        'social_links' => 'array',
        'qualifications' => 'array',
        'portfolio' => 'array',
        'skills' => 'array',
        'certifications' => 'array',
        'achievements' => 'array',
        'career_timeline' => 'array',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'follower_count' => 'integer',
        'favorite_count' => 'integer',
        'active' => 'boolean',
        'verification_status' => \App\Domain\Specialist\VerificationStatus::class,
        'verified_at' => 'datetime',
        'punctuality' => 'decimal:2',
        'attendance' => 'decimal:2',
        'response_rate' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        if ($this->photoMedia) {
            return $this->photoMedia->url;
        }
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    /**
     * Portfolio media attached to this specialist via Media polymorphic relation
     */
    public function portfolioMedia()
    {
        return $this->morphMany(Media::class, 'attachable')
            ->where('visibility', 'public')
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('created_at', 'desc');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Authentication account for Specialist OS access
     */
    public function account()
    {
        return $this->hasOne(SpecialistAccount::class);
    }

    /**
     * New Domain relationship: specialist_assignments (Availability Engine)
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SpecialistAssignment::class);
    }

    /**
     * Legacy relationship: salon_specialist pivot (used by existing controllers/seeders)
     * @deprecated Use assignments() for new features
     */
    public function salons(): BelongsToMany
    {
        return $this->belongsToMany(Salon::class, 'salon_specialist')
            ->withPivot('role', 'availability', 'services', 'active')
            ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function customerRelationships(): HasMany
    {
        return $this->hasMany(CustomerSpecialist::class);
    }

    /**
     * Journey domain relationships
     */
    public function journeyMetrics(): HasMany
    {
        return $this->hasMany(\App\Models\JourneyMetric::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(\App\Models\JourneyGoal::class);
    }

    public function milestoneAchievements(): HasMany
    {
        return $this->hasMany(\App\Models\JourneyMilestoneAchievement::class);
    }

    public function growthOpportunities(): HasMany
    {
        return $this->hasMany(\App\Models\JourneyGrowthOpportunity::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SpecialistActivity::class)->orderBy('created_at', 'desc');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_specialist', 'specialist_id', 'customer_id')
            ->withPivot('is_favorite', 'is_following', 'rating_given', 'total_bookings', 'total_spent', 'relationship_score', 'last_interaction_at', 'provider_id', 'relationship_origin', 'acquisition_source', 'source_provider_id')
            ->withTimestamps();
    }

    public function verifications()
    {
        return $this->hasMany(SpecialistVerification::class);
    }

    public function careerEvents()
    {
        return $this->hasMany(SpecialistCareerEvent::class);
    }

    public function expertise()
    {
        return $this->hasMany(SpecialistExpertise::class)->orderBy('created_at', 'desc');
    }

    /**
     * Finance Domain: Get ledger account for this specialist
     */
    public function ledgerAccount()
    {
        return $this->morphOne(\App\Domain\Finance\Ledger\LedgerAccount::class, 'owner');
    }

    /**
     * Finance Domain: Get settlements where this specialist is the recipient
     */
    public function settlementsAsRecipient()
    {
        return $this->morphMany(\App\Domain\Finance\Settlement\Settlement::class, 'recipient');
    }

    /**
     * Finance Domain: Get payouts to this specialist
     */
    public function payouts()
    {
        return $this->morphMany(\App\Domain\Finance\Payout\Payout::class, 'recipient');
    }

    public function subscription()
    {
        return $this->morphOne(Subscription::class, 'subscribable');
    }

    /**
     * Check if specialist has active Pro subscription
     */
    public function isPro(): bool
    {
        return $this->subscription && $this->subscription->isActive();
    }

    /**
     * Check if specialist is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status?->isVerified() ?? false;
    }

    /**
     * Get follower count (customers following this specialist)
     */
    public function getFollowerCountAttribute(): int
    {
        return $this->customerRelationships()->where('is_following', true)->count();
    }

    /**
     * Get favorite count (customers who favorited this specialist)
     */
    public function getFavoriteCountAttribute(): int
    {
        return $this->customerRelationships()->where('is_favorite', true)->count();
    }
}
