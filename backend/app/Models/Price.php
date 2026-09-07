<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Price extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'price_book_id',
        'amount',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(PlatformProduct::class, 'product_id');
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    /**
     * Format the amount as a readable string with currency.
     */
    public function formatted(): string
    {
        return number_format($this->amount, 0) . ' ' . $this->currency;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
