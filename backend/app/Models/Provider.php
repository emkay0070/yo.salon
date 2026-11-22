<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProviderType;
use App\Domain\Provider\ProviderTypeRegistry;
use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;

class Provider extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Transient properties tracking explicit type inputs for compatibility and conflict validation.
     */
    public ?string $suppliedLegacyType = null;
    public ?string $suppliedExplicitProviderTypeId = null;

    /**
     * Clear legacy conflict tracking (used by factories when synchronizing legacy overrides).
     */
    public function clearLegacyConflict(): void
    {
        $this->suppliedExplicitProviderTypeId = null;
    }

    /**
     * Phase 5: provider_type_id is the sole authoritative classification.
     * Legacy `type` column is dropped from the database; input 'type' is
     * accepted via compatibility mutator and mapped to provider_type_id.
     */
    protected static function booted(): void
    {
        static::saving(function (Provider $provider) {
            $registry = app(ProviderTypeRegistry::class);

            $hasLegacyType = $provider->suppliedLegacyType !== null && $provider->suppliedLegacyType !== '';
            $hasProviderTypeId = !empty($provider->provider_type_id);

            if (!$hasLegacyType && !$hasProviderTypeId) {
                if (!$provider->exists) {
                    throw new \InvalidArgumentException(
                        'A Provider cannot be created without a Provider Type.'
                    );
                }

                // Unrelated update: leave the canonical relationship untouched.
                return;
            }

            if ($hasLegacyType && !$registry->isSupported($provider->suppliedLegacyType)) {
                throw new UnsupportedProviderTypeError($provider->suppliedLegacyType);
            }

            if ($hasLegacyType && !$provider->exists && !$registry->isAvailableForNewRegistration($provider->suppliedLegacyType)) {
                throw new UnsupportedProviderTypeError($provider->suppliedLegacyType);
            }

            if ($hasProviderTypeId) {
                $providerType = ProviderType::find($provider->provider_type_id);

                if (!$providerType) {
                    throw new UnsupportedProviderTypeError(
                        (string) $provider->provider_type_id
                    );
                }

                $typeKey = $providerType->key;

                if (!$registry->isSupported($typeKey)) {
                    throw new UnsupportedProviderTypeError($typeKey);
                }

                if (!$provider->exists && !$registry->isAvailableForNewRegistration($typeKey)) {
                    throw new UnsupportedProviderTypeError($typeKey);
                }

                if ($hasLegacyType && $provider->suppliedExplicitProviderTypeId && $provider->suppliedLegacyType !== $typeKey) {
                    throw new \InvalidArgumentException(sprintf(
                        'Conflicting provider types: legacy type "%s" does not match provider_type_id "%s" (%s).',
                        $provider->suppliedLegacyType,
                        $provider->provider_type_id,
                        $typeKey
                    ));
                }

                return;
            }

            // Legacy-only creation: resolve provider_type_id through registry
            $typeId = $registry->getTypeId($provider->suppliedLegacyType);

            if (!$typeId) {
                throw new UnsupportedProviderTypeError($provider->suppliedLegacyType);
            }

            $provider->provider_type_id = $typeId;
        });

        static::saved(function (Provider $provider) {
            $provider->suppliedLegacyType = null;
            $provider->suppliedExplicitProviderTypeId = null;
        });
    }

    /**
     * Compatibility mutator: allow callers passing 'type' => 'spa' during create/update
     * to transparently resolve and populate 'provider_type_id'.
     */
    public function setTypeAttribute(?string $value): void
    {
        $this->suppliedLegacyType = $value;

        if ($value !== null && $value !== '') {
            $registry = app(ProviderTypeRegistry::class);
            $typeId = $registry->getTypeId($value);

            if (!$typeId) {
                throw new UnsupportedProviderTypeError($value);
            }

            if (!$this->suppliedExplicitProviderTypeId || $this->suppliedExplicitProviderTypeId === $typeId) {
                $this->attributes['provider_type_id'] = $typeId;
                unset($this->relations['providerType']);
            }
        }
    }

    public function setProviderTypeIdAttribute(?string $value): void
    {
        $this->attributes['provider_type_id'] = $value;
        $this->suppliedExplicitProviderTypeId = $value;
        unset($this->relations['providerType']);
    }

    /**
     * Compatibility accessor: transparently return the canonical Provider Type key
     * so existing API serializers and consumers accessing $provider->type continue
     * to work seamlessly after the database column is dropped.
     */
    public function getTypeAttribute(): ?string
    {
        return $this->providerTypeKey();
    }

    protected $fillable = [
        'organization_id',
        'type',
        'provider_type_id',
        'status',
        'display_name',
        'slug',
        'description',
        'story',
        'mission',
        'logo',
        'cover_image',
        'phone',
        'email',
        'website',
        'location',
        'social_links',
        'settings',
        'timezone',
        'availability_settings',
        'rating',
        'review_count',
        'years_in_business',
        'awards',
        'gallery',
        'active',
        'business_category_id',
    ];

    /**
     * Provider types
     */
    const TYPE_SALON = 'salon';
    const TYPE_SPECIALIST = 'independent_specialist';
    const TYPE_SPA = 'spa';
    const TYPE_BEAUTY_SCHOOL = 'beauty_school';
    const TYPE_MOBILE_TEAM = 'mobile_team';
    // TODO: TYPE_SUPPLIER is for Commerce/Catalog domain, not operational provider types
    const TYPE_SUPPLIER = 'supplier';

    protected $casts = [
        'location' => 'array',
        'social_links' => 'array',
        'settings' => 'array',
        'rating' => 'decimal:2',
        'active' => 'boolean',
    ];

    protected $appends = [
        'type',
        'logo_url',
        'cover_image_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logoMedia?->url;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->coverImageMedia?->url;
    }

    // Relationships
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function primaryLocation(): HasOne
    {
        return $this->hasOne(Location::class)->where('is_primary', true);
    }

    public function getLocationIdAttribute(): ?string
    {
        return $this->primaryLocation?->id;
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_providers')
            ->withPivot('id', 'visits', 'joined_at')
            ->withTimestamps();
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo');
    }

    public function coverImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_image');
    }

    public function specialists(): HasMany
    {
        return $this->hasMany(Specialist::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SpecialistAssignment::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function bookingConfig(): HasOne
    {
        return $this->hasOne(ProviderBookingConfig::class);
    }

    public function brandExperience(): HasOne
    {
        return $this->hasOne(BrandExperience::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function providerType(): BelongsTo
    {
        return $this->belongsTo(ProviderType::class, 'provider_type_id');
    }

    /**
     * The canonical Provider Type key resolved through provider_type_id.
     */
    public function providerTypeKey(): ?string
    {
        return $this->providerType?->key;
    }

    /**
     * Whether this Provider is classified as the given operational type key.
     */
    public function hasProviderType(string $typeKey): bool
    {
        return $this->providerTypeKey() === $typeKey;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Filter by Provider Type key through the indexed provider_type_id FK.
     * Unknown keys intentionally return no results, matching the previous
     * ENUM-based behaviour without falling back to another type.
     */
    public function scopeByType($query, string $type)
    {
        $typeId = app(ProviderTypeRegistry::class)->getTypeId($type);

        if ($typeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('provider_type_id', $typeId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get the provider type definition from the registry
     */
    public function getTypeDefinition(): \App\Domain\Provider\ProviderTypeDefinition
    {
        $typeKey = $this->providerTypeKey();

        if ($typeKey === null) {
            throw new UnsupportedProviderTypeError(
                (string) $this->provider_type_id
            );
        }

        return app(ProviderTypeRegistry::class)->for($typeKey);
    }
}
