<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistCareerEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'type',
        'title',
        'description',
        'date',
        'salon_id',
        'assignment_id',
        'data',
        'icon',
        'is_public',
    ];

    protected $casts = [
        'date' => 'date',
        'data' => 'array',
        'is_public' => 'boolean',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class);
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('date', 'asc');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Event types
    const TYPE_MILESTONE = 'milestone';
    const TYPE_EMPLOYMENT = 'employment';
    const TYPE_ACHIEVEMENT = 'achievement';
    const TYPE_AWARD = 'award';
    const TYPE_CERTIFICATION = 'certification';
    const TYPE_PROMOTION = 'promotion';
    const TYPE_SALON_CHANGE = 'salon_change';
    const TYPE_SKILL_MASTERY = 'skill_mastery';
}
