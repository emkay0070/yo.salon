<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSalon
{
    /**
     * Boot the trait to add global scope and event listeners
     */
    protected static function bootBelongsToSalon()
    {
        // Add global scope to automatically filter by salon_id
        static::addGlobalScope('salon', function (Builder $query) {
            if (auth()->check()) {
                $salonId = auth()->user()->currentSalon()?->id;
                if ($salonId) {
                    $query->where('salon_id', $salonId);
                }
            }
        });

        // Automatically set salon_id on creation
        static::creating(function ($model) {
            if (auth()->check() && empty($model->salon_id)) {
                $salonId = auth()->user()->currentSalon()?->id;
                if ($salonId) {
                    $model->salon_id = $salonId;
                }
            }
        });
    }

    /**
     * Get the salon relationship
     */
    public function salon()
    {
        return $this->belongsTo(\App\Models\Salon::class);
    }

    /**
     * Query scope to get records without salon filtering
     */
    public function scopeWithoutSalonScope($query)
    {
        return $query->withoutGlobalScope('salon');
    }

    /**
     * Query scope to get records for a specific salon
     */
    public function scopeForSalon($query, $salonId)
    {
        return $query->withoutGlobalScope('salon')->where('salon_id', $salonId);
    }
}
