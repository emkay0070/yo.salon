<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BusinessCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_type',
        'slug',
        'name',
        'short_name',
        'description',
        'icon',
        'is_active',
        'sort_order',
        'default_experience_family_id',
        'default_specialist_label',
        'default_specialist_plural',
        'default_appointment_label',
        'default_customer_term',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function serviceTemplates(): HasMany
    {
        return $this->hasMany(BusinessCategoryServiceTemplate::class, 'business_category_id')
            ->orderBy('sort_order');
    }

    public function experienceFamily(): BelongsTo
    {
        return $this->belongsTo(BrandExperience::class, 'default_experience_family_id');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'business_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeByProviderType($query, string $providerType)
    {
        return $query->where('provider_type', $providerType);
    }
}
