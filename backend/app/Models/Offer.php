<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Offer extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'discount_config',
        'start_date',
        'end_date',
        'terms',
        'image',
        'active',
        'usage_limit',
        'usage_count',
    ];

    protected $casts = [
        'discount_config' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Check if offer is currently active
     */
    public function isActive(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = now()->toDateString();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    /**
     * Check if offer has reached usage limit
     */
    public function hasReachedLimit(): bool
    {
        if ($this->usage_limit === null) {
            return false;
        }
        return $this->usage_count >= $this->usage_limit;
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Get active offers for a salon
     */
    public static function getActiveForSalon(string $salonId)
    {
        return self::where('salon_id', $salonId)
            ->where('active', true)
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
