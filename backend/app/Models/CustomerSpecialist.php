<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerSpecialist extends Model
{
    use HasFactory;
    protected $table = 'customer_specialist';

    public $incrementing = false;
    protected $primaryKey = ['customer_id', 'specialist_id', 'provider_id'];

    protected $fillable = [
        'customer_id',
        'specialist_id',
        'provider_id',
        'first_booking_id',
        'last_booking_id',
        'total_bookings',
        'total_spent',
        'is_favorite',
        'is_following',
        'rating_given',
        'notes',
        'relationship_score',
        'last_seen_at',
        'last_interaction_at',
        'notifications_enabled',
        'notification_preferences',
        'relationship_origin',
        'acquisition_source',
        'source_provider_id',
    ];

    protected $casts = [
        'total_bookings' => 'integer',
        'total_spent' => 'decimal:2',
        'is_favorite' => 'boolean',
        'is_following' => 'boolean',
        'rating_given' => 'decimal:2',
        'relationship_score' => 'integer',
        'last_seen_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'notifications_enabled' => 'boolean',
        'notification_preferences' => 'array',
    ];

    /**
     * Relationship origin types
     */
    const ORIGIN_SALON = 'salon';
    const ORIGIN_INDEPENDENT = 'independent';
    const ORIGIN_PRE_EXISTING = 'pre_existing';

    /**
     * Acquisition source types
     */
    const SOURCE_BOOKING = 'booking';
    const SOURCE_PROFILE = 'profile';
    const SOURCE_INVITATION = 'invitation';
    const SOURCE_DIRECT_LINK = 'direct_link';
    const SOURCE_REFERRAL = 'referral';

    /**
     * The customer in this relationship
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The specialist in this relationship
     */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    /**
     * The provider/workspace where this relationship exists
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * The source provider where the relationship originated (if different from current provider)
     */
    public function sourceProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'source_provider_id');
    }

    /**
     * The first booking that established this relationship
     */
    public function firstBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'first_booking_id');
    }

    /**
     * The most recent booking in this relationship
     */
    public function lastBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'last_booking_id');
    }

    /**
     * Increment relationship score based on interaction type
     */
    public function incrementScore(int $points = 1): void
    {
        $this->increment('relationship_score', $points);
        $this->touch();
    }

    /**
     * Update last interaction timestamp
     */
    public function updateLastInteraction(): void
    {
        $this->update([
            'last_interaction_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Check if this is an active relationship (recent interaction)
     */
    public function isActive(): bool
    {
        return $this->last_interaction_at && 
               $this->last_interaction_at->gt(now()->subMonths(6));
    }

    /**
     * Get relationship strength level
     */
    public function getStrengthLevel(): string
    {
        if ($this->relationship_score >= 100) return 'loyal';
        if ($this->relationship_score >= 50) return 'regular';
        if ($this->relationship_score >= 20) return 'emerging';
        return 'new';
    }
}
