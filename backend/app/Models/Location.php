<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Location extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'provider_id',
        'name',
        'slug',
        'address',
        'city',
        'country',
        'lat',
        'lng',
        'phone',
        'email',
        'is_primary',
        'is_active',
        'opening_hours',
        'timezone',
        'description',
        'is_open',
        'accepting_online_bookings',
        'parking_info',
        'schedule_mode',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'is_open' => 'boolean',
        'accepting_online_bookings' => 'boolean',
        'opening_hours' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }


    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LocationSchedule::class);
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpecialistAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }
}
