<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Customer extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'photo',
        'notes',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function portalAccount(): HasOne
    {
        return $this->hasOne(PortalAccount::class);
    }

    public function salonRelationships(): HasMany
    {
        return $this->hasMany(CustomerSalon::class);
    }

    public function salons(): BelongsToMany
    {
        return $this->belongsToMany(Salon::class, 'customer_salon')
            ->withPivot('visits', 'notes', 'joined_at', 'loyalty_tier', 'wallet_balance', 'preferred_staff_id', 'is_blocked', 'block_reason')
            ->withTimestamps();
    }

    public function preference(): HasOne
    {
        return $this->hasOne(CustomerPreference::class);
    }

    public function favoriteServices(): HasMany
    {
        return $this->hasMany(CustomerFavoriteService::class);
    }

    /**
     * Find customer by phone or email
     */
    public static function findByContact(string $contact): ?self
    {
        return self::where('phone', $contact)
            ->orWhere('email', $contact)
            ->first();
    }

    /**
     * Check if customer has a portal account
     */
    public function hasPortalAccount(): bool
    {
        return $this->portalAccount !== null;
    }

    /**
     * Get customer's relationship with a specific salon
     */
    public function getSalonRelationship(string $salonId): ?CustomerSalon
    {
        return $this->salonRelationships()->where('salon_id', $salonId)->first();
    }
}
