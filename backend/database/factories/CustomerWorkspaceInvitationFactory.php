<?php

namespace Database\Factories;

use App\Models\CustomerWorkspaceInvitation;
use App\Models\Customer;
use App\Models\Specialist;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerWorkspaceInvitationFactory extends Factory
{
    protected $model = CustomerWorkspaceInvitation::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'specialist_id' => Specialist::factory(),
            'target_provider_id' => Provider::factory(),
            'source_provider_id' => null,
            'channel' => CustomerWorkspaceInvitation::CHANNEL_SPECIALIST_DIRECT,
            'status' => CustomerWorkspaceInvitation::STATUS_SENT,
            'sent_at' => now(),
            'opened_at' => null,
            'accepted_at' => null,
            'declined_at' => null,
            'expires_at' => null,
            'metadata' => null,
        ];
    }

    public function sent(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerWorkspaceInvitation::STATUS_SENT,
            'sent_at' => now(),
            'opened_at' => null,
            'accepted_at' => null,
            'declined_at' => null,
        ]);
    }

    public function opened(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerWorkspaceInvitation::STATUS_OPENED,
            'opened_at' => now(),
        ]);
    }

    public function accepted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerWorkspaceInvitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function declined(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerWorkspaceInvitation::STATUS_DECLINED,
            'declined_at' => now(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerWorkspaceInvitation::STATUS_EXPIRED,
            'expires_at' => now()->subDays(1),
        ]);
    }

    public function specialistDirect(): self
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CustomerWorkspaceInvitation::CHANNEL_SPECIALIST_DIRECT,
        ]);
    }

    public function platformSuggestion(): self
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CustomerWorkspaceInvitation::CHANNEL_PLATFORM_SUGGESTION,
        ]);
    }

    public function referral(): self
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CustomerWorkspaceInvitation::CHANNEL_REFERRAL,
            'metadata' => ['referring_customer_id' => Customer::factory()],
        ]);
    }

    public function directLink(): self
    {
        return $this->state(fn (array $attributes) => [
            'channel' => CustomerWorkspaceInvitation::CHANNEL_DIRECT_LINK,
        ]);
    }

    public function withExpiration(int $hours = 48): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addHours($hours),
        ]);
    }

    public function withoutExpiration(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    public function withSourceProvider(?Provider $provider = null): self
    {
        return $this->state(fn (array $attributes) => [
            'source_provider_id' => $provider?->id ?? Provider::factory(),
        ]);
    }

    public function withMetadata(array $metadata): self
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => fake()->randomElement([
                CustomerWorkspaceInvitation::STATUS_SENT,
                CustomerWorkspaceInvitation::STATUS_OPENED,
            ]),
        ]);
    }
}
