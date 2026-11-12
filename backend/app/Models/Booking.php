<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\BookingService as BookingServiceModel;
use App\Relations\BookingServiceBelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasUuids, HasFactory;
    
    protected $fillable = [
        'location_id',
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
        'operational_state',
        'payment_status',
        'payment_strategy',
        'deposit_required',
        'amount_due',
        'amount_paid',
        'amount_remaining',
        'payment_expires_at',
        'payment_snapshot',
        'idempotency_key',
        'notes',
        'slot_locked_until',
        'rescheduled_from_booking_id',
        // Operational floor tracking
        'arrived_at',
        'waiting_since',
        'queue_position',
        // Grace period tracking
        'grace_period_started_at',
        'grace_period_ends_at',
    ];

    protected $casts = [
        'date'            => 'date',
        'time'            => 'string', // Store as HH:MM string, not datetime
        'slot_locked_until' => 'datetime',
        'payment_expires_at' => 'datetime',
        'deposit_required'  => 'boolean',
        'payment_snapshot'  => 'array',
        // Operational floor
        'arrived_at'      => 'datetime',
        'waiting_since'   => 'datetime',
        'queue_position'  => 'integer',
        // Grace period tracking
        'grace_period_started_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
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
     * The physical location where this booking occurs.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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

    public function services(): BookingServiceBelongsToMany
    {
        return new BookingServiceBelongsToMany(
            Service::query(),
            $this,
            'booking_service',
            'booking_id',
            'service_id',
            'id',
            'id'
        );
    }

    public function bookingServices()
    {
        return $this->hasMany(BookingService::class);
    }

    public function resourceAllocations()
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    public function paymentRequests()
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function resolution()
    {
        return $this->hasOne(BookingResolution::class);
    }

    public function resolutions()
    {
        return $this->hasMany(BookingResolution::class);
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(Booking::class, 'rescheduled_from_booking_id');
    }

    public function rescheduledTo()
    {
        return $this->hasOne(Booking::class, 'rescheduled_from_booking_id');
    }
}
