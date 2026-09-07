<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JourneyMilestoneDefinition extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'title',
        'description',
        'icon',
        'category',
        'tier',
        'trigger_criteria',
        'is_active',
    ];

    protected $casts = [
        'trigger_criteria' => 'array',
        'is_active' => 'boolean',
    ];

    public function achievements(): HasMany
    {
        return $this->hasMany(JourneyMilestoneAchievement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
