<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BusinessCategoryServiceTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_category_id',
        'service_template_id',
        'sort_order',
        'is_recommended',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_recommended' => 'boolean',
    ];

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function serviceTemplate(): BelongsTo
    {
        return $this->belongsTo(ServiceTemplate::class);
    }

    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
