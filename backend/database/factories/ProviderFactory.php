<?php

namespace Database\Factories;

use App\Models\Provider;
use App\Models\ProviderType;
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
     * Both the canonical provider_type_id and the legacy compatibility `type`
     * are established explicitly; factories do not rely on model events to
     * infer the foreign key.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => function () {
                return \App\Models\Organization::factory()->create()->id;
            },
            'provider_type_id' => $this->providerTypeId('salon'),
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
     * Keep legacy-only overrides (for example `create(['type' => 'spa'])`)
     * consistent with the canonical FK while preserving those test paths.
     * Explicit conflicting values can still be exercised via Provider::create.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Provider $provider) {
            if (!$provider->suppliedLegacyType) {
                return;
            }

            $typeId = ProviderType::byKey($provider->suppliedLegacyType)->value('id');

            if ($typeId !== null && $provider->provider_type_id !== $typeId) {
                $provider->provider_type_id = $typeId;
                $provider->clearLegacyConflict();
            }
        });
    }

    /**
     * Indicate that the provider uses the given operational Provider Type.
     */
    public function ofType(string $typeKey): self
    {
        return $this->state(fn () => [
            'provider_type_id' => $this->providerTypeId($typeKey),
        ]);
    }

    public function forOrganization(\App\Models\Organization $organization): self
    {
        return $this->state(fn () => [
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Indicate that the provider is an independent specialist.
     */
    public function independentSpecialist(): self
    {
        return $this->ofType(Provider::TYPE_SPECIALIST);
    }

    /**
     * Indicate that the provider is a spa.
     */
    public function spa(): self
    {
        return $this->ofType(Provider::TYPE_SPA);
    }

    /**
     * Indicate that the provider is a beauty school.
     */
    public function beautySchool(): self
    {
        return $this->ofType(Provider::TYPE_BEAUTY_SCHOOL);
    }

    /**
     * Indicate that the provider is a mobile team.
     */
    public function mobileTeam(): self
    {
        return $this->ofType(Provider::TYPE_MOBILE_TEAM);
    }

    /**
     * Resolve a seeded Provider Type id. Tests fail loudly if the catalogue is
     * missing rather than silently falling back to an incorrect type.
     */
    private function providerTypeId(string $typeKey): string
    {
        $id = ProviderType::byKey($typeKey)->value('id');

        if ($id === null) {
            throw new \RuntimeException(sprintf(
                'ProviderType [%s] is not seeded; cannot build a Provider factory state.',
                $typeKey
            ));
        }

        return $id;
    }
}
