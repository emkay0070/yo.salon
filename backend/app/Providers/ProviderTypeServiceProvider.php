<?php

namespace App\Providers;

use App\Domain\Provider\ProviderTypeDefinition;
use App\Domain\Provider\ProviderTypeRegistry;
use Illuminate\Support\ServiceProvider;

class ProviderTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderTypeRegistry::class, function () {
            $registry = new ProviderTypeRegistry();

            // Register all canonical provider types
            $registry->register('salon', new ProviderTypeDefinition(
                type: 'salon',
                displayName: 'Salon',
                isIndependent: false,
                isOwnerManaged: false,
            ));

            $registry->register('independent_specialist', new ProviderTypeDefinition(
                type: 'independent_specialist',
                displayName: 'Independent Specialist',
                isIndependent: true,
                isOwnerManaged: true,
            ));

            $registry->register('spa', new ProviderTypeDefinition(
                type: 'spa',
                displayName: 'Spa',
                isIndependent: false,
                isOwnerManaged: false,
            ));

            $registry->register('beauty_school', new ProviderTypeDefinition(
                type: 'beauty_school',
                displayName: 'Beauty School',
                isIndependent: false,
                isOwnerManaged: false,
            ));

            $registry->register('mobile_team', new ProviderTypeDefinition(
                type: 'mobile_team',
                displayName: 'Mobile Team',
                isIndependent: false,
                isOwnerManaged: false,
            ));

            return $registry;
        });
    }

    public function boot(): void
    {
        //
    }
}
