<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceInsight extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'run_id',
        'provider_id',
        'type',
        'severity',
        'title',
        'description',
        'evidence',
        'confidence',
        'recommended_action',
        'deep_link',
        'is_dismissed',
        'is_predictive',
        'dismissed_at',
        'metadata',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'evidence' => 'array',
        'metadata' => 'array',
        'is_dismissed' => 'boolean',
        'is_predictive' => 'boolean',
        'dismissed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(IntelligenceRun::class, 'run_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
