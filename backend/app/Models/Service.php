<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\BelongsToSalon;

class Service extends Model
{
    use HasUuids, BelongsToSalon;
    
    protected $fillable = [
        'salon_id',
        'name',
        'description',
        'price',
        'duration',
        'category',
        'image_path',
        'active',
    ];

    protected $casts = [
        'price' => 'float',
        'active' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
