<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PaymentAccount represents the financial account that receives funds.
 * 
 * This is distinct from PaymentMethod:
 * - PaymentMethod: HOW the customer pays (MTN MoMo, Visa, Cash)
 * - PaymentAccount: WHOSE account receives the money (Salon's Flutterwave, Yo.Salon routing)
 * 
 * This abstraction enables the dual payment ownership model:
 * 1. Salon-owned merchant accounts (existing setup)
 * 2. Yo.Salon-facilitated connected/subaccount structures
 */
class PaymentAccount extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\PaymentAccountFactory::new();
    }

    protected $fillable = [
        'salon_id',
        'specialist_id',
        'provider',
        'account_type',
        'external_account_id',
        'external_account_reference',
        'account_name',
        'is_active',
        'verification_status',
        'settlement_currency',
        'settlement_schedule',
        'metadata',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Account types
     */
    const TYPE_MERCHANT = 'merchant';           // Salon's own merchant account
    const TYPE_SUBACCOUNT = 'subaccount';       // Yo.Salon-connected subaccount
    const TYPE_ROUTING = 'routing';             // Yo.Salon routing account
    const TYPE_MANUAL = 'manual';               // Manual/offline settlement

    /**
     * Verification statuses
     */
    const VERIFICATION_PENDING = 'pending';
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_FAILED = 'failed';
    const VERIFICATION_DISABLED = 'disabled';

    /**
     * Settlement schedules
     */
    const SETTLEMENT_DAILY = 'daily';
    const SETTLEMENT_WEEKLY = 'weekly';
    const SETTLEMENT_MONTHLY = 'monthly';
    const SETTLEMENT_INSTANT = 'instant';

    /**
     * Get the salon that owns this payment account.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the specialist that owns this payment account (if applicable).
     */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    /**
     * Get payment methods that route through this account.
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get transactions that settled to this account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope for active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for verified accounts.
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED);
    }

    /**
     * Scope for default accounts.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for merchant accounts (salon-owned).
     */
    public function scopeMerchant($query)
    {
        return $query->where('account_type', self::TYPE_MERCHANT);
    }

    /**
     * Scope for subaccount accounts (Yo.Salon-facilitated).
     */
    public function scopeSubaccount($query)
    {
        return $query->where('account_type', self::TYPE_SUBACCOUNT);
    }

    /**
     * Check if this account can receive payments.
     */
    public function canReceivePayments(): bool
    {
        return $this->is_active 
            && $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    /**
     * Get the display name for this account.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->account_name) {
            return $this->account_name;
        }

        return ucfirst($this->provider) . ' ' . ucfirst($this->account_type);
    }
}
