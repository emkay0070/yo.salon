<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailabilityWindow extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'version' => 'integer',
    ];

    public function assignment()
    {
        return $this->belongsTo(SpecialistAssignment::class, 'assignment_id');
    }
}
