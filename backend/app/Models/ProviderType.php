<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProviderType is the database-backed catalogue of supported provider type
 * identities. It works alongside the existing PHP ProviderTypeRegistry which
 * maps each registry key to operational domain behavior (isIndependent,
 * isOwnerManaged, etc.).
 *
 * Cache invalidation is handled centrally in
 * App\Providers\ProviderTypeServiceProvider::boot(); do not register duplicate
 * listeners here.
 */
class ProviderType extends Model
{
    use HasUuids;

    protected $table = 'provider_types';

    protected $fillable = [
        'key',
        'name',
        'is_active',
        'is_independent',
        'is_owner_managed',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_independent' => 'boolean',
        'is_owner_managed' => 'boolean',
    ];

    /**
     * All providers that use this provider type.
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class, 'provider_type_id');
    }

    /**
     * Scope to active types only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find a type by its stable key.
     */
    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }
}
