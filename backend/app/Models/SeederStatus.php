<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeederStatus extends Model
{
    protected $fillable = [
        'seeder_class',
        'display_name',
        'description',
        'category',
        'is_seeded',
        'last_seeded_at',
        'last_seeded_by',
        'records_count',
        'metadata',
        'is_required',
        'priority',
    ];

    protected $casts = [
        'is_seeded' => 'boolean',
        'is_required' => 'boolean',
        'last_seeded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSeeded($query)
    {
        return $query->where('is_seeded', true);
    }

    public function scopeNotSeeded($query)
    {
        return $query->where('is_seeded', false);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOrderByPriority($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('display_name');
    }

    public function markAsSeeded(string $seededBy, int $recordsCount = 0, array $metadata = [])
    {
        $this->update([
            'is_seeded' => true,
            'last_seeded_at' => now(),
            'last_seeded_by' => $seededBy,
            'records_count' => $recordsCount,
            'metadata' => $metadata,
        ]);
    }

    public function markAsUnseeded()
    {
        $this->update([
            'is_seeded' => false,
            'last_seeded_at' => null,
            'last_seeded_by' => null,
            'records_count' => 0,
        ]);
    }
}
