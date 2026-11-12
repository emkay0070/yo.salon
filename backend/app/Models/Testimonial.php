<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Testimonial extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $fillable = [
        'provider_id',
        'location_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'rating',
        'content',
        'visible',
        'consent_given',
        'consent_date',
        'status',
        'media_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'visible' => 'boolean',
        'consent_given' => 'boolean',
        'consent_date' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForLocation($query, string $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeWithConsent($query)
    {
        return $query->where('consent_given', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('consent_given', true)
                    ->where('status', 'approved')
                    ->where('visible', true);
    }
}
