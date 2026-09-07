<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialistTimeOff extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'specialist_time_off';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function assignment()
    {
        return $this->belongsTo(SpecialistAssignment::class, 'assignment_id');
    }
}
