<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialistAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'commission_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'services' => 'array',
    ];

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function schedules()
    {
        return $this->hasMany(AssignmentSchedule::class, 'assignment_id');
    }

    public function timeOff()
    {
        return $this->hasMany(SpecialistTimeOff::class, 'assignment_id');
    }

    public function availabilityWindows()
    {
        return $this->hasMany(AvailabilityWindow::class, 'assignment_id');
    }

    /**
     * Journey domain relationships
     */
    public function journeyMetrics()
    {
        return $this->hasMany(\App\Models\JourneyMetric::class, 'specialist_assignment_id');
    }

    public function goals()
    {
        return $this->hasMany(\App\Models\JourneyGoal::class, 'specialist_assignment_id');
    }

    public function growthOpportunities()
    {
        return $this->hasMany(\App\Models\JourneyGrowthOpportunity::class, 'specialist_assignment_id');
    }
}
