<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceData extends Model
{
    protected $fillable = [
        'category',
        'key',
        'label',
        'value',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'is_system',
        'parent_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function parent()
    {
        return $this->belongsTo(ReferenceData::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ReferenceData::class, 'parent_id');
    }
}
