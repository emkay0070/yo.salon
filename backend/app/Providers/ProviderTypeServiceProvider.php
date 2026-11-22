<?php

namespace App\Providers;

use App\Domain\Provider\ProviderTypeRegistry;
use Illuminate\Support\ServiceProvider;

class ProviderTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderTypeRegistry::class, function () {
            return new ProviderTypeRegistry();
        });
    }

    public function boot(): void
    {
        // Single authoritative location for ProviderType cache invalidation.
        // Consolidated here (not in the ProviderType model) to avoid duplicate
        // listener registration across the model boot() and the service provider.
        \App\Models\ProviderType::created(fn () => app(ProviderTypeRegistry::class)->clearCache());
        \App\Models\ProviderType::updated(fn () => app(ProviderTypeRegistry::class)->clearCache());
        \App\Models\ProviderType::deleted(fn () => app(ProviderTypeRegistry::class)->clearCache());
    }
}
