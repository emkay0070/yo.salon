<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CustomerTimeline extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'salon_id',
        'booking_id',
        'service_name',
        'category',
        'specialist_name',
        'specialist_id',
        'provider_name',
        'provider_id',
        'price',
        'rating',
        'before_photo',
        'after_photo',
        'notes',
        'service_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating' => 'decimal:1',
        'service_date' => 'datetime',
    ];

    protected $appends = [
        'before_photo_url',
        'after_photo_url',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function beforePhotoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'before_photo');
    }

    public function afterPhotoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'after_photo');
    }

    public function getBeforePhotoUrlAttribute(): ?string
    {
        if ($this->before_photo && \Illuminate\Support\Str::isUuid($this->before_photo)) {
            return $this->beforePhotoMedia?->url;
        }
        if ($this->before_photo) {
            return asset('storage/' . $this->before_photo);
        }
        return null;
    }

    public function getAfterPhotoUrlAttribute(): ?string
    {
        if ($this->after_photo && \Illuminate\Support\Str::isUuid($this->after_photo)) {
            return $this->afterPhotoMedia?->url;
        }
        if ($this->after_photo) {
            return asset('storage/' . $this->after_photo);
        }
        return null;
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByCategory($query, $category)
    {
        if ($category === 'all') {
            return $query;
        }
        return $query->where('category', $category);
    }

    public function scopeByMonth($query, $year, $month)
    {
        return $query->whereYear('service_date', $year)
            ->whereMonth('service_date', $month);
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('service_date', 'desc');
    }
}
