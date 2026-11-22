<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LocationService
{
    /**
     * Create a Location for a Provider.
     *
     * The owning Provider row is locked for the duration of the transaction so
     * concurrent creations for the same Provider are serialized. The first
     * Location becomes primary; every subsequent Location is non-primary.
     */
    public function createLocation(Provider $provider, array $attributes): Location
    {
        return DB::transaction(function () use ($provider, $attributes) {
            $lockedProvider = $this->lockProvider($provider);

            return $this->createForLockedProvider($lockedProvider, $attributes);
        });
    }

    /**
     * Return the Provider's first Location, creating it as primary when none
     * exists. Used by idempotent provisioning paths such as independent
     * specialist workspace setup.
     */
    public function firstOrCreateForProvider(Provider $provider, array $attributes): Location
    {
        return DB::transaction(function () use ($provider, $attributes) {
            $lockedProvider = $this->lockProvider($provider);

            $existing = Location::query()
                ->where('provider_id', $lockedProvider->getKey())
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->createForLockedProvider($lockedProvider, $attributes);
        });
    }

    /**
     * Make the given Location the Provider's primary Location.
     *
     * The Provider row is locked, ownership is verified, the previous primary
     * is demoted, and the target is promoted inside a single transaction. The
     * partial unique index remains the final integrity safeguard.
     *
     * This only changes the primary designation. Bookings keep their own
     * location_id values and are never rewritten by this operation.
     *
     * @throws InvalidArgumentException When the Location does not belong to the Provider.
     */
    public function switchPrimary(Provider $provider, Location $target): Location
    {
        return DB::transaction(function () use ($provider, $target) {
            $lockedProvider = $this->lockProvider($provider);

            $targetLocation = Location::query()
                ->whereKey($target->getKey())
                ->where('provider_id', $lockedProvider->getKey())
                ->first();

            if (! $targetLocation) {
                throw new InvalidArgumentException(sprintf(
                    'Location %s does not belong to Provider %s.',
                    $target->getKey(),
                    $lockedProvider->getKey()
                ));
            }

            Location::query()
                ->where('provider_id', $lockedProvider->getKey())
                ->where('is_primary', true)
                ->where('id', '!=', $targetLocation->getKey())
                ->update(['is_primary' => false]);

            if (! $targetLocation->is_primary) {
                $targetLocation->forceFill(['is_primary' => true])->save();
            }

            return $targetLocation->refresh();
        });
    }

    private function lockProvider(Provider $provider): Provider
    {
        return Provider::query()
            ->whereKey($provider->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function createForLockedProvider(Provider $provider, array $attributes): Location
    {
        $alreadyHasPrimary = Location::query()
            ->where('provider_id', $provider->getKey())
            ->where('is_primary', true)
            ->exists();

        // Never trust a caller-supplied primary flag. The first Location is
        // primary; all later Locations are non-primary unless switched through
        // switchPrimary().
        unset($attributes['provider_id'], $attributes['is_primary']);

        $attributes['provider_id'] = $provider->getKey();
        $attributes['is_primary'] = ! $alreadyHasPrimary;

        return Location::create($attributes);
    }
}
