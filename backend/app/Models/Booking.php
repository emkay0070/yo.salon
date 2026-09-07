<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasUuids, HasFactory;
    
    protected $fillable = [
        'salon_id',
        'provider_id',
        'customer_id',
        'staff_id',
        'specialist_id',
        'service_id',
        'date',
        'time',
        'start_time',
        'end_time',
        'status',
        'payment_status',
        'payment_strategy',
        'deposit_required',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'payment_expires_at',
        'payment_snapshot',
        'customer_salon_id',
        'idempotency_key',
        'notes',
        'slot_locked_until'
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime',
        'slot_locked_until' => 'datetime',
        'payment_expires_at' => 'datetime',
        'deposit_required' => 'boolean',
        'payment_snapshot' => 'array',
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
     * Alias for backward compatibility — some controllers still eager-load 'salon'.
     * The booking may have a salon_id for branch-level context.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function customerSalon(): BelongsTo
    {
        return $this->belongsTo(CustomerSalon::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_service');
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
