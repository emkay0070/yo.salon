<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ResourceRequirement extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'service_id',
        'resource_type',
        'required_capabilities',
        'count',
        'min_capacity',
    ];

    protected $casts = [
        'required_capabilities' => 'array',
        'count' => 'integer',
        'min_capacity' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
