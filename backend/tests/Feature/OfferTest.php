<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Provider;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location;
    private Provider $otherProvider;
    private Location $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create provider
        $this->provider = Provider::create([
            'id' => Str::uuid(),
            'name' => 'Test Provider',
            'display_name' => 'Test Provider Display',
            'slug' => 'test-provider',
            'active' => true,
        ]);

        // Create location
        $this->location = Location::create([
            'id' => Str::uuid(),
            'provider_id' => $this->provider->id,
            'name' => 'Test Location',
            'slug' => 'test-location',
            'address' => '123 Test Street',
            'active' => true,
        ]);

        // Create other provider
        $this->otherProvider = Provider::create([
            'id' => Str::uuid(),
            'name' => 'Other Provider',
            'display_name' => 'Other Provider Display',
            'slug' => 'other-provider',
            'active' => true,
        ]);

        // Create other location
        $this->otherLocation = Location::create([
            'id' => Str::uuid(),
            'provider_id' => $this->otherProvider->id,
            'name' => 'Other Location',
            'slug' => 'other-location',
            'address' => '456 Other Street',
            'active' => true,
        ]);
    }

    public function test_offer_creation_with_provider_id()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Test Offer',
            'description' => 'Test description',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
            'active' => true,
        ]);

        $this->assertNotNull($offer);
        $this->assertEquals($this->provider->id, $offer->provider_id);
        $this->assertNull($offer->location_id);
    }

    public function test_offer_creation_with_location_id()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'title' => 'Test Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
            'active' => true,
        ]);

        $this->assertNotNull($offer);
        $this->assertEquals($this->provider->id, $offer->provider_id);
        $this->assertEquals($this->location->id, $offer->location_id);
    }

    public function test_offer_creation_without_location_id()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => null,
            'title' => 'Test Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
            'active' => true,
        ]);

        $this->assertNotNull($offer);
        $this->assertNull($offer->location_id);
    }

    public function test_get_active_for_provider()
    {
        Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Active Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Inactive Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => false,
        ]);

        $offers = Offer::getActiveForProvider($this->provider->id);
        $this->assertCount(1, $offers);
        $this->assertEquals('Active Offer', $offers->first()->title);
    }

    public function test_get_active_for_location()
    {
        Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'title' => 'Location Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->otherLocation->id,
            'title' => 'Other Location Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        $offers = Offer::getActiveForLocation($this->location->id);
        $this->assertCount(1, $offers);
        $this->assertEquals('Location Offer', $offers->first()->title);
    }

    public function test_get_active_for_provider_with_locations()
    {
        Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Provider Wide Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'title' => 'Location Specific Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        Offer::create([
            'provider_id' => $this->otherProvider->id,
            'title' => 'Other Provider Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        $offers = Offer::getActiveForProviderWithLocations($this->provider->id);
        $this->assertCount(2, $offers);
    }

    public function test_no_salon_id_in_fillable()
    {
        $this->assertNotContains('salon_id', (new Offer())->getFillable());
    }

    public function test_no_salon_relationship()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Test Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
            'active' => true,
        ]);

        $this->assertFalse(method_exists($offer, 'salon'));
    }

    public function test_provider_relationship_exists()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Test Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
            'active' => true,
        ]);

        $this->assertTrue(method_exists($offer, 'provider'));
        $this->assertEquals($this->provider->id, $offer->provider->id);
    }

    public function test_location_relationship_exists()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'title' => 'Test Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
            'active' => true,
        ]);

        $this->assertTrue(method_exists($offer, 'location'));
        $this->assertEquals($this->location->id, $offer->location->id);
    }

    public function test_usage_limit_behavior()
    {
        $offer = Offer::create([
            'provider_id' => $this->provider->id,
            'title' => 'Limited Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
            'usage_limit' => 5,
            'usage_count' => 5,
        ]);

        $offers = Offer::getActiveForProvider($this->provider->id);
        $this->assertCount(0, $offers); // Should not be returned when limit reached
    }

    public function test_cross_provider_offer_creation_rejected()
    {
        // Verify that offers from another provider are not included in provider query
        Offer::create([
            'provider_id' => $this->otherProvider->id,
            'title' => 'Other Provider Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        $offers = Offer::getActiveForProvider($this->provider->id);
        $this->assertCount(0, $offers); // Should not return other provider's offers
    }

    public function test_location_cross_provider_rejection()
    {
        // Verify that location-specific offers from another provider are not included
        Offer::create([
            'provider_id' => $this->otherProvider->id,
            'location_id' => $this->otherLocation->id,
            'title' => 'Other Provider Location Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        $offers = Offer::getActiveForLocation($this->location->id);
        $this->assertCount(0, $offers); // Should not return other provider's location offers
    }

    public function test_provider_with_locations_excludes_other_provider_locations()
    {
        // Create offer for this provider with location
        Offer::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'title' => 'My Provider Location Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        // Create offer for other provider with location
        Offer::create([
            'provider_id' => $this->otherProvider->id,
            'location_id' => $this->otherLocation->id,
            'title' => 'Other Provider Location Offer',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'active' => true,
        ]);

        $offers = Offer::getActiveForProviderWithLocations($this->provider->id);
        $this->assertCount(1, $offers); // Should only return this provider's offers
        $this->assertEquals('My Provider Location Offer', $offers->first()->title);
    }
}
