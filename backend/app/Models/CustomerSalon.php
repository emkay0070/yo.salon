<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerSalon extends \Illuminate\Database\Eloquent\Model
{
    use HasUuids;

    protected $table = 'customer_salon';

    protected $fillable = [
        'customer_id',
        'salon_id',
        'visits',
        'notes',
        'joined_at',
        'loyalty_tier',
        'wallet_balance',
        'preferred_staff_id',
        'is_blocked',
        'block_reason',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'wallet_balance' => 'decimal:2',
        'is_blocked' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'preferred_staff_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_salon_id');
    }
}
