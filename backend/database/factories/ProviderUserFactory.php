<?php

namespace Database\Factories;

use App\Models\ProviderUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderUserFactory extends Factory
{
    protected $model = ProviderUser::class;

    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'user_id' => \App\Models\User::factory(),
            'provider_id' => \App\Models\Provider::factory(),
            'role' => 'staff',
            'authority_scope' => 'location_scoped',
        ];
    }

    /**
     * Provider-wide authority scope (owner/manager can access all locations).
     */
    public function providerWide(): static
    {
        return $this->state(fn (array $attributes) => [
            'authority_scope' => 'provider_wide',
        ]);
    }

    /**
     * Owner role with provider-wide authority.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
        ]);
    }

    /**
     * Manager role with provider-wide authority.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'manager',
            'authority_scope' => 'provider_wide',
        ]);
    }
}
