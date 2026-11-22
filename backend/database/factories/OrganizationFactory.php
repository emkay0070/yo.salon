<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name'          => $this->faker->company() . ' Group',
            'slug'          => $this->faker->unique()->slug(),
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => Organization::STATUS_ACTIVE,
            'email'         => $this->faker->unique()->companyEmail(),
            'phone'         => $this->faker->phoneNumber(),
            'owner_user_id' => function () {
                return User::factory()->create()->id;
            },
        ];
    }

    public function personal(): self
    {
        return $this->state(fn () => [
            'type' => Organization::TYPE_PERSONAL,
        ]);
    }

    public function ownedBySpecialist(\App\Models\SpecialistAccount $specialistAccount): self
    {
        return $this->state(fn () => [
            'owner_user_id'               => null,
            'owner_specialist_account_id' => $specialistAccount->id,
            'type'                        => Organization::TYPE_PERSONAL,
        ]);
    }

    public function ownedByUser(User $user): self
    {
        return $this->state(fn () => [
            'owner_user_id'               => $user->id,
            'owner_specialist_account_id' => null,
            'type'                        => Organization::TYPE_BUSINESS,
        ]);
    }
}
