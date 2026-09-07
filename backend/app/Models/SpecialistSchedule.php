<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecialistSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'specialist_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start_time',
        'break_end_time',
        'is_available',
        'effective_date',
        'expires_date',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'break_start_time' => 'datetime:H:i:s',
        'break_end_time' => 'datetime:H:i:s',
        'is_available' => 'boolean',
        'effective_date' => 'date',
        'expires_date' => 'date',
    ];

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }
}
