<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToSalon;

class Transaction extends Model
{
    use HasFactory, BelongsToSalon;

    protected $fillable = [
        'salon_id',
        'booking_id',
        'customer_id',
        'payment_method_id',
        'payment_account_id',
        'type',
        'status',
        'gross_amount',
        'gateway_fee',
        'platform_fee',
        'tax_amount',
        'net_amount',
        'currency',
        'notes',
        'provider_reference',
        'internal_reference',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'gross_amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

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

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }
}
