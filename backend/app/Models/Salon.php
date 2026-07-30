<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Salon extends Model
{
    use HasUuids;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'whatsapp',
        'phone',
        'email',
        'address',
        'city',
        'lat',
        'lng',
        'opening_hours',
        'booking_deposit_enabled',
        'deposit_type',
        'deposit_value',
        'deposit_required_for',
        'deposit_min_service_amount',
    ];

    protected $casts = [
        'opening_hours' => 'array',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
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
        return $this->hasOne(Subscription::class);
    }

    public function brandExperience()
    {
        return $this->hasOne(BrandExperience::class);
    }
}
