<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * PaymentProfile answers: "Where do we send this person's money?"
 * 
 * This represents a payout destination for a Specialist or Staff member.
 * It is completely separate from PaymentMethod (which is how customers pay).
 */
class PaymentProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'method',
        'phone_number',
        'account_number',
        'account_name',
        'bank_code',
        'bank_name',
        'label',
        'is_verified',
        'is_default',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The owner of this payment profile (Specialist or Staff)
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
