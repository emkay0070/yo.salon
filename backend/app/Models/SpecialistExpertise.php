<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistExpertise extends Model
{
    use HasUuids;

    protected $table = 'specialist_expertise';

    protected $fillable = [
        'specialist_id',
        'craft_taxonomy_id',
        'name',
        'skill_level',
    ];

    protected $casts = [
        'skill_level' => 'string',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function craftTaxonomy(): BelongsTo
    {
        return $this->belongsTo(CraftTaxonomy::class);
    }
}
