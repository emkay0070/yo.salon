<?php

namespace Database\Factories;

use App\Models\NotificationJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationJobFactory extends Factory
{
    protected $model = NotificationJob::class;

    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'type' => fake()->randomElement(['sms', 'email']),
            'recipient' => fake()->randomElement([fake()->phoneNumber(), fake()->email()]),
            'subject' => fake()->sentence(),
            'message' => fake()->sentence(),
            'data' => [],
            'status' => 'pending',
            'attempts' => 0,
            'scheduled_at' => null,
            'sent_at' => null,
            'error_message' => null,
            'related_type' => null,
            'related_id' => null,
        ];
    }

    /**
     * SMS notification job
     */
    public function sms(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sms',
            'recipient' => fake()->phoneNumber(),
            'subject' => null,
        ]);
    }

    /**
     * Email notification job
     */
    public function email(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
            'recipient' => fake()->email(),
            'subject' => fake()->sentence(),
        ]);
    }

    /**
     * Pending status
     */
    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'attempts' => 0,
            'sent_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Queued status
     */
    public function queued(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'queued',
            'attempts' => 0,
        ]);
    }

    /**
     * Sent status
     */
    public function sent(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'error_message' => null,
        ]);
    }

    /**
     * Failed status
     */
    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'attempts' => fake()->numberBetween(1, 3),
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * With scheduled time
     */
    public function scheduled(): self
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => now()->addMinutes(fake()->numberBetween(5, 60)),
        ]);
    }

    /**
     * Scheduled for past (ready to process)
     */
    public function scheduledPast(): self
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * With related entity
     */
    public function forBooking(): self
    {
        return $this->state(fn (array $attributes) => [
            'related_type' => 'booking',
            'related_id' => fake()->uuid(),
        ]);
    }

    /**
     * With provider data
     */
    public function withProviderData(): self
    {
        return $this->state(fn (array $attributes) => [
            'data' => [
                'provider_id' => fake()->uuid(),
            ],
        ]);
    }

    /**
     * With retry attempts
     */
    public function withAttempts(int $attempts = 1): self
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => $attempts,
        ]);
    }
}
