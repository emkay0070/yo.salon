<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyGoalMilestone extends Model
{
    use HasUuids;

    protected $fillable = [
        'goal_id',
        'title',
        'description',
        'criteria',
        'craft_taxonomy_id',
        'order',
        'status',
        'achieved_at',
        'metadata',
    ];

    protected $casts = [
        'criteria' => 'array',
        'achieved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(JourneyGoal::class);
    }

    public function craftTaxonomy(): BelongsTo
    {
        return $this->belongsTo(CraftTaxonomy::class);
    }
}
