<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BrandExperience extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'experience_family',
        'logo',
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_heading',
        'font_body',
        'background_image',
        'custom_domain',
        'white_label_enabled',
    ];

    protected $casts = [
        'white_label_enabled' => 'boolean',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }
}
