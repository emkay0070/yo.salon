<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyPoint extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'customer_id',
        'provider_id',
        'points_earned',
        'points_redeemed',
        'balance',
        'tier',
        'tier_progress',
        'earned_at',
        'redeemed_at',
        'description',
        'metadata',
    ];

    protected $casts = [
        'points_earned' => 'integer',
        'points_redeemed' => 'integer',
        'balance' => 'integer',
        'tier_progress' => 'integer',
        'earned_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Get customer's current balance for a provider
     */
    public static function getCustomerBalance(string $customerId, string $providerId): int
    {
        return self::where('customer_id', $customerId)
            ->where('provider_id', $providerId)
            ->sum('balance');
    }

    /**
     * Get customer's current tier for a provider
     */
    public static function getCustomerTier(string $customerId, string $providerId): string
    {
        $latest = self::where('customer_id', $customerId)
            ->where('provider_id', $providerId)
            ->orderBy('earned_at', 'desc')
            ->first();

        return $latest ? $latest->tier : 'bronze';
    }

    /**
     * Get customer's tier progress for a provider
     */
    public static function getTierProgress(string $customerId, string $providerId): array
    {
        $latest = self::where('customer_id', $customerId)
            ->where('provider_id', $providerId)
            ->orderBy('earned_at', 'desc')
            ->first();

        if (!$latest) {
            return [
                'tier' => 'bronze',
                'progress' => 0,
                'points_to_next' => 100,
                'next_tier' => 'silver',
            ];
        }

        $tierThresholds = [
            'bronze' => 0,
            'silver' => 100,
            'gold' => 500,
            'platinum' => 1000,
        ];

        $currentTier = $latest->tier;
        $currentPoints = self::getCustomerBalance($customerId, $providerId);

        $nextTier = match($currentTier) {
            'bronze' => 'silver',
            'silver' => 'gold',
            'gold' => 'platinum',
            'platinum' => null,
            default => null,
        };

        $pointsToNext = $nextTier ? ($tierThresholds[$nextTier] - $currentPoints) : 0;

        return [
            'tier' => $currentTier,
            'progress' => $latest->tier_progress,
            'points_to_next' => max(0, $pointsToNext),
            'next_tier' => $nextTier,
        ];
    }
}
