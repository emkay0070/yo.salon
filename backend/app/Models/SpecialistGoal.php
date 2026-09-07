<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistGoal extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'goal_type',
        'target_value',
        'metric',
        'period_type',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'uuid';

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
