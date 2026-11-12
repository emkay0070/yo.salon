<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GiftCard extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'provider_id',
        'code',
        'amount',
        'balance',
        'currency',
        'status',
        'purchased_by',
        'redeemed_by',
        'redeemed_at',
        'expires_at',
        'issued_at',
        'cancelled_at',
        'active',
        'message',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'active' => 'boolean',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'purchased_by');
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'redeemed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if gift card is valid for redemption
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->status === 'cancelled' || $this->status === 'expired') {
            return false;
        }

        if ($this->status === 'fully_redeemed') {
            return false;
        }

        if ($this->balance <= 0) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if gift card can be partially redeemed
     */
    public function canPartiallyRedeem(float $amount): bool
    {
        return $this->isValid() && $this->balance >= $amount;
    }

    /**
     * Generate a unique gift card code
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 12));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get current balance for a gift card code
     */
    public static function getBalance(string $code, string $providerId): ?float
    {
        $giftCard = self::where('code', $code)
            ->where('provider_id', $providerId)
            ->first();

        return $giftCard ? $giftCard->balance : null;
    }

    /**
     * Find gift card by code within provider scope
     */
    public static function findByCode(string $code, string $providerId): ?self
    {
        return self::where('code', $code)
            ->where('provider_id', $providerId)
            ->first();
    }
}
