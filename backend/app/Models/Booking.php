<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\BelongsToSalon;

class Booking extends Model
{
    use HasUuids, BelongsToSalon;
    
    protected $fillable = [
        'salon_id',
        'customer_id',
        'staff_id',
        'service_id',
        'date',
        'time',
        'status',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerSalon(): BelongsTo
    {
        return $this->belongsTo(CustomerSalon::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
