<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FeatureFlag extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'feature_key',
        'enabled',
        'enabled_at',
        'enabled_by',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    /**
     * Check if feature is enabled for a salon
     */
    public static function isEnabledForSalon(string $salonId, string $featureKey): bool
    {
        return self::where('salon_id', $salonId)
            ->where('feature_key', $featureKey)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * Enable feature for salon
     */
    public static function enableForSalon(string $salonId, string $featureKey, string $userId, string $reason = null): self
    {
        return self::updateOrCreate(
            [
                'salon_id' => $salonId,
                'feature_key' => $featureKey,
            ],
            [
                'enabled' => true,
                'enabled_at' => now(),
                'enabled_by' => $userId,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Disable feature for salon
     */
    public static function disableForSalon(string $salonId, string $featureKey): void
    {
        self::where('salon_id', $salonId)
            ->where('feature_key', $featureKey)
            ->update([
                'enabled' => false,
                'enabled_at' => null,
            ]);
    }
}
