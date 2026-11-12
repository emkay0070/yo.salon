<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialistBreak extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'started_by_actor_type',
        'started_by_actor_id',
        'started_at',
        'expected_end_at',
        'actual_end_at',
        'ended_by_actor_type',
        'ended_by_actor_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expected_end_at' => 'datetime',
        'actual_end_at' => 'datetime',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function startedByActor()
    {
        return $this->morphTo('started_by_actor');
    }

    public function endedByActor()
    {
        return $this->morphTo('ended_by_actor');
    }
}
