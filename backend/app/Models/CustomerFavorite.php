<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerFavorite extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'salon_id',
        'favoritable_type',
        'favoritable_id',
        'collection_id',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CustomerCollection::class, 'collection_id');
    }

    public function favoritable()
    {
        return $this->morphTo();
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('favoritable_type', $type);
    }

    public function scopeByCollection($query, $collectionId)
    {
        return $query->where('collection_id', $collectionId);
    }
}
