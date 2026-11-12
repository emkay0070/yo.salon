<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\PaymentMethodFactory::new();
    }

    protected $fillable = [
        'provider_id',
        'payment_account_id',
        'provider',
        'type',
        'display_name',
        'account_name',
        'account_identifier',
        'merchant_id',
        'api_key',
        'api_secret',
        'api_subscription_key',
        'webhook_secret',
        'environment',
        'currency',
        'is_primary',
        'is_active',
        'metadata',
        'credentials_verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'credentials_verified_at' => 'datetime',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'api_subscription_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'api_subscription_key',
        'webhook_secret',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function hasValidCredentials(): bool
    {
        return !empty($this->merchant_id) &&
               !empty($this->api_key) &&
               !empty($this->api_subscription_key) &&
               $this->credentials_verified_at !== null;
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeForProvider($query, string $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeProduction($query)
    {
        return $query->where('environment', 'production');
    }

    public function scopeSandbox($query)
    {
        return $query->where('environment', 'sandbox');
    }
}
