<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistActivity extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public $timestamps = false;

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    // Activity types
    const TYPE_BOOKING = 'booking';
    const TYPE_REVIEW = 'review';
    const TYPE_FOLLOW = 'follow';
    const TYPE_ACHIEVEMENT = 'achievement';
    const TYPE_SERVICE_ADDED = 'service_added';
    const TYPE_CERTIFICATION = 'certification';

    /**
     * Create a new activity record
     */
    public static function log(string $specialistId, string $type, array $data = []): self
    {
        return self::create([
            'specialist_id' => $specialistId,
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Get formatted activity message
     */
    public function getMessageAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BOOKING => "New booking: {$this->data['service_name']} with {$this->data['customer_name']}",
            self::TYPE_REVIEW => "New review: {$this->data['rating']} stars from {$this->data['customer_name']}",
            self::TYPE_FOLLOW => "New follower: {$this->data['customer_name']}",
            self::TYPE_ACHIEVEMENT => "Achievement unlocked: {$this->data['achievement']}",
            self::TYPE_SERVICE_ADDED => "New service added: {$this->data['service_name']}",
            self::TYPE_CERTIFICATION => "New certification: {$this->data['certification']}",
            default => 'Activity logged',
        };
    }
}
