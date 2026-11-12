<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Provider;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'user_id' => null,
            'customer_id' => null,
            'staff_id' => null,
            'type' => 'info',
            'category' => 'general',
            'priority' => 'normal',
            'icon' => 'bell',
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'data' => [],
            'read_at' => null,
        ];
    }

    /**
     * For a specific user
     */
    public function forUser(?User $user = null): self
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
            'customer_id' => null,
            'staff_id' => null,
        ]);
    }

    /**
     * For a specific customer
     */
    public function forCustomer(?Customer $customer = null): self
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'customer_id' => $customer?->id ?? Customer::factory(),
            'staff_id' => null,
        ]);
    }

    /**
     * Booking notification
     */
    public function booking(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'booking_created',
            'category' => 'booking',
            'priority' => 'high',
            'icon' => 'calendar',
            'title' => 'New Booking',
            'message' => 'A new booking has been created',
            'data' => [
                'booking_id' => fake()->uuid(),
                'customer_name' => fake()->name(),
                'service_names' => fake()->words(2, true),
            ],
        ]);
    }

    /**
     * Payment notification
     */
    public function payment(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment_confirmed',
            'category' => 'payment',
            'priority' => 'high',
            'icon' => 'wallet',
            'title' => 'Payment Received',
            'message' => 'Payment has been confirmed',
            'data' => [
                'booking_id' => fake()->uuid(),
                'amount' => fake()->numberBetween(10000, 500000),
                'payment_method' => 'MTN MoMo',
            ],
        ]);
    }

    /**
     * Review notification
     */
    public function review(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'review_created',
            'category' => 'review',
            'priority' => 'normal',
            'icon' => 'star',
            'title' => 'New Review',
            'message' => 'A new review has been submitted',
            'data' => [
                'review_id' => fake()->uuid(),
                'rating' => fake()->numberBetween(3, 5),
            ],
        ]);
    }

    /**
     * Testimonial notification
     */
    public function testimonial(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'testimonial_created',
            'category' => 'testimonial',
            'priority' => 'normal',
            'icon' => 'quote',
            'title' => 'New Testimonial',
            'message' => 'A new testimonial has been submitted',
            'data' => [
                'testimonial_id' => fake()->uuid(),
                'rating' => fake()->numberBetween(3, 5),
            ],
        ]);
    }

    /**
     * High priority
     */
    public function highPriority(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Urgent priority
     */
    public function urgent(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }

    /**
     * Low priority
     */
    public function lowPriority(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'low',
        ]);
    }

    /**
     * Read notification
     */
    public function read(): self
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    /**
     * Unread notification
     */
    public function unread(): self
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * With action URL
     */
    public function withActionUrl(): self
    {
        return $this->state(fn (array $attributes) => [
            'action_url' => fake()->url(),
        ]);
    }
}
