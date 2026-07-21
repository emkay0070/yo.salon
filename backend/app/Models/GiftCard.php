<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GiftCard extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'code',
        'amount',
        'currency',
        'purchased_by',
        'redeemed_by',
        'redeemed_at',
        'expires_at',
        'active',
        'message',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'active' => 'boolean',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'purchased_by');
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'redeemed_by');
    }

    /**
     * Check if gift card is valid
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->redeemed_at !== null) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
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
}
