<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class BookingService extends Pivot
{
    use HasUuids, HasFactory;

    protected $table = 'booking_service';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_id',
        'service_id',
        'start_time',
        'end_time',
        'started_at',
        'started_by_actor_type',
        'started_by_actor_id',
        'actual_end_at',
        'completed_by_actor_type',
        'completed_by_actor_id',
        'session_status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'started_at' => 'datetime',
        'actual_end_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BookingService $pivot) {
            if (empty($pivot->id)) {
                $pivot->id = (string) Str::uuid();
            }
        });
    }

    public function booking(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function service(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    public function startedByActor()
    {
        return $this->morphTo('started_by_actor');
    }

    public function completedByActor()
    {
        return $this->morphTo('completed_by_actor');
    }
}
