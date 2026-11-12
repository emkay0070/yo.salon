<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceScore extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'run_id',
        'provider_id',
        'subject_type',
        'subject_id',
        'score_type',
        'score',
        'confidence',
        'sample_size',
        'status',
        'score_breakdown',
        'metadata',
    ];

    protected $casts = [
        'score' => 'integer',
        'confidence' => 'decimal:2',
        'sample_size' => 'integer',
        'score_breakdown' => 'array',
        'metadata' => 'array',
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
