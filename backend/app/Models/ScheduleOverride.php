<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleOverride extends Model
{
    use HasUuids;

    protected $fillable = [
        'assignment_id',
        'date',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'is_working_day',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'is_working_day' => 'boolean',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class, 'assignment_id');
    }
}
