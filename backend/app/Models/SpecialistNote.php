<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialistNote extends Model
{
    protected $fillable = [
        'customer_id',
        'specialist_id',
        'salon_id',
        'booking_id',
        'note',
        'note_type',
        'tags',
        'is_private',
        'is_important',
        'customer_visible',
        'customer_viewed_at',
        'metadata',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'is_private' => 'boolean',
        'is_important' => 'boolean',
        'customer_visible' => 'boolean',
        'customer_viewed_at' => 'datetime',
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

    public function salon()
    {
        return $this->belongsTo(Salon::class);
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

    public function scopeBySalon($query, string $salonId)
    {
        return $query->where('salon_id', $salonId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('note_type', $type);
    }

    public function scopeCustomerVisible($query)
    {
        return $query->where('customer_visible', true);
    }

    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function markAsViewedByCustomer()
    {
        $this->update([
            'customer_viewed_at' => now(),
        ]);
    }
}
