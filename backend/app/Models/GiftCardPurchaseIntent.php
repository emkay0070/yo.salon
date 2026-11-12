<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GiftCardPurchaseIntent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'provider_id',
        'amount',
        'currency',
        'status', // pending, paid, failed
        'payment_method',
        'provider_reference',
        'message',
        'gift_card_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
