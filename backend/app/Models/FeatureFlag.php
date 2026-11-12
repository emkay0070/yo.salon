<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FeatureFlag extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_id',
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

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    /**
     * Check if feature is enabled for a provider
     */
    public static function isEnabledForProvider(string $providerId, string $featureKey): bool
    {
        return self::where('provider_id', $providerId)
            ->where('feature_key', $featureKey)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * Enable feature for provider
     */
    public static function enableForProvider(string $providerId, string $featureKey, string $userId, string $reason = null): self
    {
        return self::updateOrCreate(
            [
                'provider_id' => $providerId,
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
     * Disable feature for provider
     */
    public static function disableForProvider(string $providerId, string $featureKey): void
    {
        self::where('provider_id', $providerId)
            ->where('feature_key', $featureKey)
            ->update([
                'enabled' => false,
                'enabled_at' => null,
            ]);
    }
}
