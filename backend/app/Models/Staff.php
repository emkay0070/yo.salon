<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\BelongsToSalon;

class Staff extends Model
{
    use HasUuids, BelongsToSalon;
    
    protected $fillable = [
        'salon_id',
        'name',
        'phone',
        'email',
        'specializations',
        'availability',
        'photo',
        'active',
        'role',
    ];

    protected $casts = [
        'specializations' => 'array',
        'availability' => 'array',
        'active' => 'boolean',
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
}
