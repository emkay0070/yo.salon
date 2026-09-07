<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_working_day' => 'boolean',
    ];

    public function assignment()
    {
        return $this->belongsTo(SpecialistAssignment::class, 'assignment_id');
    }
}
