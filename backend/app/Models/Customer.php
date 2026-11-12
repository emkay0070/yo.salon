<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        // Guest identity
        'is_guest',
        'guest_token',
        'guest_provider_id',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
    ];

    /**
     * Create an anonymous walk-in guest, scoped to a specific provider.
     *
     * A guest is a real Customer row (bookings, sessions and payments point to it)
     * but has no phone/email and is isolated per-provider. Two walk-ins at two
     * different salons produce two independent guest records, never merged.
     *
     * The guest can be promoted to a real customer later by a deliberate staff
     * action (adding their phone/email).
     */
    public static function createGuest(string $providerId, string $displayName = 'Walk-in Guest'): self
    {
        return self::create([
            'name'              => $displayName,
            'phone'             => null,
            'email'             => null,
            'is_guest'          => true,
            'guest_token'       => Str::random(16),
            'guest_provider_id' => $providerId,
        ]);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function portalAccount(): HasOne
    {
        return $this->hasOne(PortalAccount::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(CustomerPreference::class);
    }

    public function favoriteServices(): HasMany
    {
        return $this->hasMany(CustomerFavoriteService::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'customer_providers')
            ->withPivot('id', 'visits', 'joined_at')
            ->withTimestamps();
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

}
