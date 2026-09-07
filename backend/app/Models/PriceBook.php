<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PriceBook extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'currency',
        'country_code',
        'is_default',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'is_default'  => 'boolean',
        'is_active'   => 'boolean',
        'valid_from'  => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Check whether this price book is currently valid.
     */
    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }
}
