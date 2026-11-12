<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profession extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function specialists(): BelongsToMany
    {
        return $this->belongsToMany(Specialist::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function craftTaxonomy(): BelongsToMany
    {
        return $this->belongsToMany(CraftTaxonomy::class, 'craft_taxonomy_profession', 'profession_id', 'craft_taxonomy_id')
            ->withTimestamps();
    }
}
