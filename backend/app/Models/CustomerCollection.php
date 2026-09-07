<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerCollection extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'salon_id',
        'name',
        'description',
        'icon',
        'is_default',
        'created_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(CustomerFavorite::class, 'collection_id');
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
