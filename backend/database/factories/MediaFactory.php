<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Provider;
use App\Models\Location;
use App\Models\User;
use App\Models\Specialist;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'disk' => 'public',
            'path' => 'uploads/' . $this->faker->uuid() . '.jpg',
            'filename' => $this->faker->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(10000, 5000000),
            'width' => $this->faker->numberBetween(800, 1920),
            'height' => $this->faker->numberBetween(600, 1080),
            'alt_text' => $this->faker->sentence(),
            'metadata' => null,
            'provider_id' => Provider::factory(),
            'location_id' => null,
            'uploaded_by' => null,
            'uploader_type' => null,
            'uploader_id' => null,
            'attachable_type' => null,
            'attachable_id' => null,
            'visibility' => Media::VISIBILITY_PUBLIC,
        ];
    }

    /**
     * Provider-level media (no location context)
     */
    public function providerLevel(): self
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => null,
        ]);
    }

    /**
     * Location-scoped media
     */
    public function forLocation(?Location $location = null): self
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => $location?->id ?? Location::factory(),
        ]);
    }

    /**
     * Public visibility
     */
    public function public(): self
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Media::VISIBILITY_PUBLIC,
        ]);
    }

    /**
     * Authenticated visibility
     */
    public function authenticated(): self
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Media::VISIBILITY_AUTHENTICATED,
        ]);
    }

    /**
     * Private visibility
     */
    public function private(): self
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Media::VISIBILITY_PRIVATE,
        ]);
    }

    /**
     * Attached to a specialist
     */
    public function forSpecialist(?Specialist $specialist = null): self
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => Specialist::class,
            'attachable_id' => $specialist?->id ?? Specialist::factory(),
            'visibility' => Media::VISIBILITY_PUBLIC,
        ]);
    }

    /**
     * Attached to a customer
     */
    public function forCustomer(?Customer $customer = null): self
    {
        return $this->state(fn (array $attributes) => [
            'attachable_type' => Customer::class,
            'attachable_id' => $customer?->id ?? Customer::factory(),
            'visibility' => Media::VISIBILITY_PRIVATE,
        ]);
    }

    /**
     * Uploaded by a user
     */
    public function uploadedByUser(?User $user = null): self
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $user?->id ?? User::factory(),
            'uploader_type' => User::class,
            'uploader_id' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Gallery media (public image)
     */
    public function gallery(): self
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/jpeg',
            'visibility' => Media::VISIBILITY_PUBLIC,
            'alt_text' => $this->faker->sentence(),
        ]);
    }

    /**
     * Service image
     */
    public function serviceImage(): self
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/jpeg',
            'visibility' => Media::VISIBILITY_PUBLIC,
            'alt_text' => $this->faker->sentence(),
        ]);
    }

    /**
     * Specialist profile photo
     */
    public function specialistPhoto(): self
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/jpeg',
            'visibility' => Media::VISIBILITY_PUBLIC,
            'alt_text' => $this->faker->name() . ' profile photo',
        ]);
    }

    /**
     * PNG image
     */
    public function png(): self
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->uuid() . '.png',
            'mime_type' => 'image/png',
        ]);
    }

    /**
     * WebP image
     */
    public function webp(): self
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->uuid() . '.webp',
            'mime_type' => 'image/webp',
        ]);
    }

    /**
     * SVG image
     */
    public function svg(): self
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->uuid() . '.svg',
            'mime_type' => 'image/svg+xml',
        ]);
    }

    /**
     * Soft deleted (archived)
     */
    public function deleted(): self
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => $this->faker->dateTime(),
        ]);
    }
}
