<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Usage extends Model
{
    use HasUuids;

    protected $fillable = [
        'subscription_id',
        'metric',
        'current_value',
        'limit',
        'period',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeMetric($query, string $metric)
    {
        return $query->where('metric', $metric);
    }

    public function scopeCurrent($query)
    {
        return $query->where('period', 'current');
    }

    public function getPercentageAttribute(): float
    {
        if ($this->limit === 0) {
            return 0;
        }

        return ($this->current_value / $this->limit) * 100;
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->limit - $this->current_value);
    }

    public function isNearLimit(int $threshold = 80): bool
    {
        return $this->percentage >= $threshold;
    }

    public function isOverLimit(): bool
    {
        return $this->current_value > $this->limit;
    }
}
