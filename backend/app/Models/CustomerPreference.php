<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'customer_salon_id',
        'salon_id',
        'preferred_staff_id',
        'notification_preferences',
        'booking_preferences',
    ];

    protected $casts = [
        'notification_preferences' => 'array',
        'booking_preferences' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerSalon(): BelongsTo
    {
        return $this->belongsTo(CustomerSalon::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'preferred_staff_id');
    }
}
