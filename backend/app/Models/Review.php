<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'booking_id',
        'provider_id',
        'service_id',
        'staff_id',
        'specialist_id',
        'customer_id',
        'subject_type',
        'subject_id',
        'rating',
        'comment',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => 'string',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }


    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Scopes for scoped reviews
    public function scopeForService($query, string $serviceId)
    {
        return $query->where('subject_type', 'service')->where('subject_id', $serviceId);
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('subject_type', 'specialist')->where('subject_id', $specialistId);
    }


    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('subject_type', 'provider')->where('subject_id', $providerId);
    }
}
