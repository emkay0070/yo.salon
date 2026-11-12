<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Resource extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'type',
        'capacity',
        'turnaround_time',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'turnaround_time' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ResourceCapability::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ResourceException::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }
}
