<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Laravel\Sanctum\HasApiTokens;

class PortalAccount extends Model implements AuthenticatableContract
{
    use HasUuids, Authenticatable, HasApiTokens;

    protected $fillable = [
        'customer_id',
        'email',
        'password',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    /**
     * Portal accounts are NOT scoped by salon_id
     * Like Uber, one account can have relationships with multiple salons
     * The customer relationship is salon-scoped, not the account itself
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if phone is verified
     */
    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Check if account is fully verified
     */
    public function isVerified(): bool
    {
        return $this->isEmailVerified() || $this->isPhoneVerified();
    }
}
