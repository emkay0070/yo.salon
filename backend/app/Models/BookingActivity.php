<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingActivity extends Model
{
    protected $fillable = [
        'booking_id',
        'type',
        'title',
        'description',
        'data',
        'actor_id',
        'actor_type',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeForBooking($query, $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('created_at', 'asc');
    }
}
