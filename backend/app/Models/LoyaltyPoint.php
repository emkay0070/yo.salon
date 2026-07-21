<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LoyaltyPoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'salon_id',
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

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get customer's current balance for a salon
     */
    public static function getCustomerBalance(string $customerId, string $salonId): int
    {
        return self::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->sum('balance');
    }

    /**
     * Get customer's current tier for a salon
     */
    public static function getCustomerTier(string $customerId, string $salonId): string
    {
        $latest = self::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->orderBy('earned_at', 'desc')
            ->first();

        return $latest ? $latest->tier : 'bronze';
    }

    /**
     * Get customer's tier progress for a salon
     */
    public static function getTierProgress(string $customerId, string $salonId): array
    {
        $latest = self::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->orderBy('earned_at', 'desc')
            ->first();

        if (!$latest) {
            return [
                'tier' => 'bronze',
                'progress' => 0,
                'points_to_next' => 100,
            ];
        }

        $tierThresholds = [
            'bronze' => 0,
            'silver' => 100,
            'gold' => 500,
            'platinum' => 1000,
        ];

        $currentTier = $latest->tier;
        $currentPoints = self::getCustomerBalance($customerId, $salonId);

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
