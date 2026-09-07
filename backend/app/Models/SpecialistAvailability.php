<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistAvailability extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'specialist_id',
        'salon_id',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_available',
        'status',
        'booking_id',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_available' => 'boolean',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where('status', 'available');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForSpecialist($query, $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function scopeForSalon($query, $salonId)
    {
        return $query->where('salon_id', $salonId);
    }
}
