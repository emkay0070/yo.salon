<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Usage extends Model
{
    use HasUuids;

    public const UNLIMITED = -1;

    protected $table = 'usage';

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
        // -1 means unlimited — no meaningful percentage
        if ($this->limit <= 0 && $this->limit === self::UNLIMITED) {
            return 0;
        }

        if ($this->limit === 0) {
            return 0;
        }

        return ($this->current_value / $this->limit) * 100;
    }

    public function getRemainingAttribute(): int
    {
        // -1 means unlimited
        if ($this->limit === self::UNLIMITED) {
            return self::UNLIMITED;
        }

        return max(0, $this->limit - $this->current_value);
    }

    public function isNearLimit(int $threshold = 80): bool
    {
        // Unlimited metrics are never near their limit
        if ($this->limit === self::UNLIMITED) {
            return false;
        }

        return $this->percentage >= $threshold;
    }

    public function isOverLimit(): bool
    {
        // Unlimited metrics can never be over-limit
        if ($this->limit === self::UNLIMITED) {
            return false;
        }

        return $this->current_value > $this->limit;
    }
}
