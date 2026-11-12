<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class ProviderUser extends Model
{
    use HasFactory;

    protected $keyType = 'uuid';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'provider_id',
        'role',
        'authority_scope',
    ];

    protected $casts = [
        'authority_scope' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function locationAuthorities()
    {
        return $this->hasMany(ProviderUserLocation::class);
    }

    /**
     * Check if this user has provider-wide authority.
     */
    public function isProviderWide(): bool
    {
        return $this->authority_scope === 'provider_wide';
    }

    /**
     * Check if this user has location-scoped authority.
     */
    public function isLocationScoped(): bool
    {
        return $this->authority_scope === 'location_scoped';
    }

    /**
     * Check if this user can access a specific location.
     */
    public function canAccessLocation(string $locationId): bool
    {
        if ($this->isProviderWide()) {
            return $this->provider->locations()->where('id', $locationId)->exists();
        }

        return $this->locationAuthorities()->where('location_id', $locationId)->exists();
    }

    /**
     * Get all permitted locations for this user.
     */
    public function getPermittedLocations()
    {
        if ($this->isProviderWide()) {
            return $this->provider->locations;
        }

        return $this->locationAuthorities()->with('location')->get()->pluck('location');
    }
}
