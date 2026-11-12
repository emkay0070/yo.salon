<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntelligenceRun extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'provider_id',
        'analysis_type',
        'schema_version',
        'algorithm_version',
        'generated_at',
        'data_through',
        'status',
        'duration_ms',
        'confidence',
        'metadata',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'data_through' => 'datetime',
        'duration_ms' => 'integer',
        'confidence' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(IntelligenceScore::class, 'run_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(IntelligenceInsight::class, 'run_id');
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(IntelligenceForecast::class, 'run_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
