<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceForecast extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'run_id',
        'provider_id',
        'forecast_type',
        'period',
        'forecast_date',
        'forecast_value',
        'confidence',
        'forecast_breakdown',
        'metadata',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'forecast_value' => 'decimal:2',
        'confidence' => 'decimal:2',
        'forecast_breakdown' => 'array',
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
