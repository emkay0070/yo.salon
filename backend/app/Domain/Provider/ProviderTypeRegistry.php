<?php

namespace App\Domain\Provider;

use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;
use App\Models\ProviderType;
use Illuminate\Support\Facades\Cache;

/**
 * ProviderTypeRegistry is the singleton that resolves provider type keys to
 * ProviderTypeDefinition value objects.
 *
 * Type definitions are loaded from the database-backed provider_types table
 * and cached for the lifetime of the request (or until invalidated). The
 * existing register() method is retained for backward compatibility and
 * supports programmatic additions alongside the DB-backed catalogue.
 *
 * All types are loaded from the database (including inactive ones) so that
 * existing Providers remain resolvable even if their type is later
 * deactivated. Use isAvailableForNewRegistration() to determine whether a
 * type can be used to create new Providers.
 */
class ProviderTypeRegistry
{
    private const CACHE_KEY = 'provider_types:registry';

    private array $definitions = [];
    private ?array $cachedDefinitions = null;

    public function register(string $type, ProviderTypeDefinition $definition): void
    {
        $this->definitions[$type] = $definition;
        $this->cachedDefinitions = null;
    }

    public function for(string $type): ProviderTypeDefinition
    {
        $all = $this->all();

        if (!isset($all[$type])) {
            throw new UnsupportedProviderTypeError($type);
        }

        return $all[$type];
    }

    public function isSupported(string $type): bool
    {
        return isset($this->all()[$type]);
    }

    /**
     * Determine whether a type is available for creating new Providers.
     *
     * A type is available for new registration only if it exists in the
     * registry and is marked active. Inactive types remain resolvable for
     * existing Providers but cannot be used to create new ones.
     */
    public function isAvailableForNewRegistration(string $type): bool
    {
        if (!isset($this->all()[$type])) {
            return false;
        }

        return $this->all()[$type]->isActive;
    }

    /**
     * Get the database id for a provider type key.
     *
     * Returns null when the type is not registered.
     */
    public function getTypeId(string $type): ?string
    {
        if (!isset($this->all()[$type])) {
            return null;
        }

        return $this->all()[$type]->id;
    }

    public function all(): array
    {
        if ($this->cachedDefinitions === null) {
            $this->cachedDefinitions = array_merge(
                $this->loadFromDatabase(),
                $this->definitions
            );
        }

        return $this->cachedDefinitions;
    }

    /**
     * Clear the in-memory and Laravel cache so the next resolution reloads
     * from the database. Called automatically by ProviderType model events.
     */
    public function clearCache(): void
    {
        $this->cachedDefinitions = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function loadFromDatabase(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $result = [];

            // Load ALL types, including inactive ones, so existing Providers
            // remain resolvable even if their type is deactivated.
            foreach (ProviderType::all() as $type) {
                $result[$type->key] = new ProviderTypeDefinition(
                    id: $type->id,
                    type: $type->key,
                    displayName: $type->name,
                    isIndependent: $type->is_independent,
                    isOwnerManaged: $type->is_owner_managed,
                    isActive: $type->is_active,
                );
            }

            return $result;
        });
    }
}
