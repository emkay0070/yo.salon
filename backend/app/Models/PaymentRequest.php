<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'location_id',
        'booking_id',
        'customer_id',
        'payment_method_id',
        'provider',
        'amount',
        'phone_number',
        'status',
        'provider_reference',
        'requested_at',
        'expires_at',
        'completed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
