<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SpecialistAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    protected $model = OrganizationMembership::class;

    public function definition(): array
    {
        return [
            'organization_id' => function () {
                return Organization::factory()->create()->id;
            },
            'user_id' => function () {
                return User::factory()->create()->id;
            },
            'specialist_account_id' => null,
            'role'   => OrganizationMembership::ROLE_MEMBER,
            'status' => OrganizationMembership::STATUS_ACTIVE,
            'accepted_at' => now(),
        ];
    }

    public function forSpecialist(SpecialistAccount $account): self
    {
        return $this->state(fn () => [
            'user_id' => null,
            'specialist_account_id' => $account->id,
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'specialist_account_id' => null,
        ]);
    }

    public function owner(): self
    {
        return $this->state(fn () => [
            'role' => OrganizationMembership::ROLE_OWNER,
        ]);
    }

    public function admin(): self
    {
        return $this->state(fn () => [
            'role' => OrganizationMembership::ROLE_ADMIN,
        ]);
    }
}
