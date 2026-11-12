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
        ];
    }
}
