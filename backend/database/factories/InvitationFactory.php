<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'email' => fake()->safeEmail(),
            'role' => fake()->randomElement(['customer', 'staff', 'specialist', 'manager', 'receptionist']),
            'target_id' => null,
            'token' => hash('sha256', Str::random(64)),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ]);
    }

    public function accepted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDays(1),
        ]);
    }

    public function staff(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'staff',
        ]);
    }

    public function customer(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'customer',
        ]);
    }

    public function specialist(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'specialist',
        ]);
    }

    public function manager(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'manager',
        ]);
    }

    public function receptionist(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'receptionist',
        ]);
    }

    public function withTarget(): self
    {
        return $this->state(fn (array $attributes) => [
            'target_id' => Str::uuid(),
        ]);
    }

    public function withoutExpiration(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }
}
