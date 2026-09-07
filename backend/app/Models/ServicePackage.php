<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ServicePackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'name',
        'description',
        'price',
        'services_included',
        'service_ids',
        'validity_days',
        'active',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'services_included' => 'integer',
        'validity_days' => 'integer',
        'active' => 'boolean',
        'service_ids' => 'array',
    ];

    protected $appends = [
        'image_url',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image && \Illuminate\Support\Str::isUuid($this->image)) {
            return $this->imageMedia?->url;
        }
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /**
     * Get active packages for a salon
     */
    public static function getActiveForSalon(string $salonId)
    {
        return self::where('salon_id', $salonId)
            ->where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
