<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Provider::TYPE_SALON,
            'status' => 'active',
            'display_name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->paragraph(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->companyEmail(),
            'active' => true,
        ];
    }

    /**
     * Indicate that the provider is an independent specialist.
     */
    public function independentSpecialist(): self
    {
        return $this->state(fn () => ['type' => Provider::TYPE_SPECIALIST]);
    }

    /**
     * Indicate that the provider is a spa.
     */
    public function spa(): self
    {
        return $this->state(fn () => ['type' => Provider::TYPE_SPA]);
    }

    /**
     * Indicate that the provider is a beauty school.
     */
    public function beautySchool(): self
    {
        return $this->state(fn () => ['type' => Provider::TYPE_BEAUTY_SCHOOL]);
    }

    /**
     * Indicate that the provider is a mobile team.
     */
    public function mobileTeam(): self
    {
        return $this->state(fn () => ['type' => Provider::TYPE_MOBILE_TEAM]);
    }
}
