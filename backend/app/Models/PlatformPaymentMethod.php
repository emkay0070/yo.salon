<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlatformPaymentMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'verification_mode',
        'is_active',
        'details',
        'credentials',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'details' => 'encrypted:array',
        'credentials' => 'encrypted:array',
        'sort_order' => 'integer',
    ];

    protected $hidden = [
        'credentials',
        'details', // Hide encrypted details from API responses
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeManual($query)
    {
        return $query->where('verification_mode', 'manual');
    }

    public function scopeAutomatic($query)
    {
        return $query->where('verification_mode', 'automatic');
    }

    public function scopeWebhook($query)
    {
        return $query->where('verification_mode', 'webhook');
    }
}
