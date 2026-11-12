<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProfessionalAssessment extends Model
{
    use HasUuids;
    protected $fillable = [
        'customer_id',
        'specialist_id',
        'provider_id',
        'location_id',
        'booking_id',
        'observed_hair_type',
        'hair_density',
        'scalp_condition',
        'hairline',
        'hair_observations',
        'observed_beard_style',
        'beard_growth_pattern',
        'beard_observations',
        'observed_skin_type',
        'skin_observations',
        'skin_conditions',
        'confidence_level',
        'notes',
        'metadata',
        'customer_reviewed',
        'customer_accepted',
        'customer_reviewed_at',
        'customer_feedback',
    ];

    protected $casts = [
        'skin_conditions' => 'array',
        'metadata' => 'array',
        'customer_reviewed' => 'boolean',
        'customer_accepted' => 'boolean',
        'customer_reviewed_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'uuid';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function specialist()
    {
        // specialist_id references specialists.id (global professional identity)
        return $this->belongsTo(Specialist::class, 'specialist_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeByCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function scopeByProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeByLocation($query, string $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopePendingReview($query)
    {
        return $query->where('customer_reviewed', false);
    }

    public function scopeAccepted($query)
    {
        return $query->where('customer_reviewed', true)->where('customer_accepted', true);
    }

    public function scopeRejected($query)
    {
        return $query->where('customer_reviewed', true)->where('customer_accepted', false);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function markAsReviewed(bool $accepted, string $feedback = null)
    {
        $this->update([
            'customer_reviewed' => true,
            'customer_accepted' => $accepted,
            'customer_reviewed_at' => now(),
            'customer_feedback' => $feedback,
        ]);
    }
}
