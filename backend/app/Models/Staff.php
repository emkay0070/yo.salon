<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Staff extends Model
{
    use HasFactory, HasUuids;
    
    protected $fillable = [
        'provider_id',
        'location_id',
        'user_id',
        'name',
        'phone',
        'email',
        'photo',
        'active',
        'commission_rate',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        // If photo is a UUID, get from media relationship
        if ($this->photo && \Illuminate\Support\Str::isUuid($this->photo)) {
            return $this->media?->url;
        }
        // If photo is a path (backward compatibility), use asset
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }
}
