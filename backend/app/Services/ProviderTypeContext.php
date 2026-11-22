<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\ProviderType;
use App\Domain\Provider\ProviderTypeRegistry;
use App\Domain\Provider\ProviderTypeDefinition;
use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ProviderTypeContext resolves the current authenticated/selected Provider's
 * type and its registered definition.
 *
 * The context uses the Provider resolved by middleware (request attributes)
 * or the authenticated user's current provider. It never accepts a
 * client-supplied provider type key directly.
 *
 * Missing providers and unsupported types are handled explicitly: the context
 * records an error rather than silently falling back to Salon.
 */
class ProviderTypeContext
{
    private ?Provider $provider = null;
    private ?ProviderType $providerType = null;
    private ?ProviderTypeDefinition $definition = null;
    private ?string $typeKey = null;
    private ?string $error = null;

    /**
     * Resolve context from an HTTP request.
     *
     * Resolution order:
     *  1. provider_id from request attributes (set by provider.context middleware)
     *  2. Authenticated user's currentProvider()
     *
     * @param  Request  $request
     * @return $this
     */
    public function resolveFromRequest(Request $request): self
    {
        if ($request->attributes->has('provider') && $request->attributes->get('provider') instanceof Provider) {
            $this->provider = $request->attributes->get('provider');
        } else {
            $providerId = $request->attributes->get('provider_id');

            if ($providerId) {
                $this->provider = Provider::find($providerId);
            }
        }

        // Fallback to the authenticated user's current provider
        if (!$this->provider && Auth::check()) {
            $this->provider = Auth::user()->currentProvider();
        }

        return $this->resolve();
    }

    /**
     * Resolve context for a specific Provider model instance.
     *
     * @param  Provider  $provider
     * @return $this
     */
    public function resolveForProvider(Provider $provider): self
    {
        $this->provider = $provider;
        return $this->resolve();
    }

    /**
     * Resolve context for a provider ID.
     *
     * @param  string  $providerId
     * @return $this
     */
    public function resolveForProviderId(string $providerId): self
    {
        $this->provider = Provider::find($providerId);
        return $this->resolve();
    }

    private function resolve(): self
    {
        // Reset state
        $this->providerType = null;
        $this->definition = null;
        $this->typeKey = null;
        $this->error = null;

        if (!$this->provider) {
            $this->error = 'No provider found in context.';
            return $this;
        }

        // provider_type_id is authoritative: resolve strictly through the
        // FK-backed relationship, never through the legacy `type` column.
        $providerType = $this->provider->providerType;

        if (!$providerType) {
            $this->error = sprintf(
                'Provider type "%s" is not supported.',
                (string) $this->provider->provider_type_id
            );
            return $this;
        }

        $typeKey = $providerType->key;

        $registry = app(ProviderTypeRegistry::class);

        if (!$registry->isSupported($typeKey)) {
            $this->error = sprintf(
                'Provider type "%s" is not supported.',
                $typeKey
            );
            return $this;
        }

        try {
            $this->definition = $registry->for($typeKey);
        } catch (UnsupportedProviderTypeError $e) {
            $this->error = $e->getMessage();
            return $this;
        }

        // The related record may be inactive: existing Providers remain
        // resolvable even if their type is deactivated. is_active metadata is
        // still exposed through toArray().
        $this->providerType = $providerType;
        $this->typeKey = $typeKey;

        return $this;
    }

    /**
     * Get the resolved Provider model, or null if not found.
     */
    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    /**
     * Get the resolved Provider ID, or null if not found.
     */
    public function getProviderId(): ?string
    {
        return $this->provider?->id;
    }

    /**
     * Get the provider type key (e.g. 'salon', 'independent_specialist').
     */
    public function getTypeKey(): ?string
    {
        return $this->typeKey;
    }

    /**
     * Get the ProviderType database record, or null.
     */
    public function getProviderType(): ?ProviderType
    {
        return $this->providerType;
    }

    /**
     * Get the ProviderTypeDefinition value object, or null.
     */
    public function getDefinition(): ?ProviderTypeDefinition
    {
        return $this->definition;
    }

    /**
     * Whether the resolved provider is an independent business.
     */
    public function isIndependent(): bool
    {
        return $this->definition?->isIndependent ?? false;
    }

    /**
     * Whether the resolved provider is owner-managed.
     */
    public function isOwnerManaged(): bool
    {
        return $this->definition?->isOwnerManaged ?? false;
    }

    /**
     * Whether the provider type is active (available for new registration).
     * Inactive types remain resolvable for existing Providers.
     */
    public function isTypeActive(): bool
    {
        return $this->providerType?->is_active ?? false;
    }

    /**
     * Whether context resolution succeeded without error.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Get the error message, if any.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Export context as an array suitable for API responses.
     */
    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider?->id,
            'provider_type' => $this->getTypeKey(),
            'provider_type_id' => $this->providerType?->id,
            'provider_type_definition' => $this->definition ? [
                'id' => $this->definition->id,
                'type' => $this->definition->type,
                'display_name' => $this->definition->displayName,
                'is_independent' => $this->definition->isIndependent,
                'is_owner_managed' => $this->definition->isOwnerManaged,
                'is_active' => $this->isTypeActive(),
            ] : null,
            'is_independent' => $this->isIndependent(),
            'is_owner_managed' => $this->isOwnerManaged(),
            'error' => $this->error,
        ];
    }
}
