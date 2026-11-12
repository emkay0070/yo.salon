<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\LocationServiceCatalogResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationServiceCatalogResolverTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;
    protected Location $location;
    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->service = Service::factory()->create(['provider_id' => $this->provider->id, 'active' => true]);
    }

    public function test_open_world_inheritance_provider_service_appears_without_location_override()
    {
        $resolver = new LocationServiceCatalogResolver();

        $services = $resolver->queryAvailableServices($this->location)
            ->where('services.id', $this->service->id)
            ->get();

        $this->assertCount(1, $services);
        $this->assertEquals($this->service->id, $services->first()->id);
    }

    public function test_explicit_disable_is_available_false_excludes_service()
    {
        $resolver = new LocationServiceCatalogResolver();

        // Explicitly disable the service at this location
        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'is_available' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAvailableServices($this->location)
            ->where('services.id', $this->service->id)
            ->get();

        $this->assertCount(0, $services);
    }

    public function test_explicit_availability_is_available_true_includes_service()
    {
        $resolver = new LocationServiceCatalogResolver();

        // Explicitly enable the service at this location
        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAvailableServices($this->location)
            ->where('services.id', $this->service->id)
            ->get();

        $this->assertCount(1, $services);
        $this->assertEquals($this->service->id, $services->first()->id);
    }

    public function test_null_inheritance_price_override_null_uses_provider_price()
    {
        $resolver = new LocationServiceCatalogResolver();

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertNull($services->pivot_price_override);
        $this->assertEquals($this->service->price, $services->price);
    }

    public function test_override_behavior_price_override_uses_location_price()
    {
        $resolver = new LocationServiceCatalogResolver();

        $locationPrice = $this->service->price + 50;

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'price_override' => $locationPrice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEqualsWithDelta($locationPrice, $services->pivot_price_override, 0.01);
    }

    public function test_override_behavior_duration_override_uses_location_duration()
    {
        $resolver = new LocationServiceCatalogResolver();

        $locationDuration = $this->service->duration + 30;

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'duration_override' => $locationDuration,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEquals($locationDuration, $services->pivot_duration_override);
    }

    public function test_override_behavior_buffer_before_override_uses_location_buffer()
    {
        $resolver = new LocationServiceCatalogResolver();

        $bufferBefore = 15;

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'buffer_before_override' => $bufferBefore,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEquals($bufferBefore, $services->pivot_buffer_before_override);
    }

    public function test_override_behavior_buffer_after_override_uses_location_buffer()
    {
        $resolver = new LocationServiceCatalogResolver();

        $bufferAfter = 10;

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'buffer_after_override' => $bufferAfter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEquals($bufferAfter, $services->pivot_buffer_after_override);
    }

    public function test_override_behavior_min_booking_notice_override_uses_location_notice()
    {
        $resolver = new LocationServiceCatalogResolver();

        $minNotice = 24;

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'min_booking_notice_override' => $minNotice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEquals($minNotice, $services->pivot_min_booking_notice_override);
    }

    public function test_override_behavior_cancellation_cutoff_override_uses_location_cutoff()
    {
        $resolver = new LocationServiceCatalogResolver();

        $cancellationCutoff = 48;

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'cancellation_cutoff_override' => $cancellationCutoff,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEquals($cancellationCutoff, $services->pivot_cancellation_cutoff_override);
    }

    public function test_override_behavior_deposit_required_override_uses_location_deposit()
    {
        $resolver = new LocationServiceCatalogResolver();

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'deposit_required_override' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertTrue((bool) $services->pivot_deposit_required_override);
    }

    public function test_override_behavior_online_booking_enabled_override_uses_location_setting()
    {
        $resolver = new LocationServiceCatalogResolver();

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'online_booking_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertFalse((bool) $services->pivot_online_booking_enabled);
    }

    public function test_override_behavior_notes_override_uses_location_notes()
    {
        $resolver = new LocationServiceCatalogResolver();

        $notes = 'Only available on weekdays at this location';

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $services = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEquals($notes, $services->pivot_notes);
    }

    public function test_location_isolation_location_a_override_does_not_affect_location_b()
    {
        $resolver = new LocationServiceCatalogResolver();

        $locationB = Location::factory()->create(['provider_id' => $this->provider->id]);

        // Add override only to location A
        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'price_override' => $this->service->price + 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Location A should have the override
        $servicesA = $resolver->queryAllServices($this->location)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEqualsWithDelta($this->service->price + 100, $servicesA->pivot_price_override, 0.01);

        // Location B should NOT have the override
        $servicesB = $resolver->queryAllServices($locationB)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertNull($servicesB->pivot_price_override);
    }

    public function test_multiple_locations_same_provider_service_resolves_independently()
    {
        $resolver = new LocationServiceCatalogResolver();

        $locationA = Location::factory()->create(['provider_id' => $this->provider->id]);
        $locationB = Location::factory()->create(['provider_id' => $this->provider->id]);

        // Different overrides for each location
        \DB::table('location_service')->insert([
            'location_id' => $locationA->id,
            'service_id' => $this->service->id,
            'price_override' => $this->service->price + 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('location_service')->insert([
            'location_id' => $locationB->id,
            'service_id' => $this->service->id,
            'price_override' => $this->service->price + 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $servicesA = $resolver->queryAllServices($locationA)
            ->where('services.id', $this->service->id)
            ->first();

        $servicesB = $resolver->queryAllServices($locationB)
            ->where('services.id', $this->service->id)
            ->first();

        $this->assertEqualsWithDelta($this->service->price + 50, $servicesA->pivot_price_override, 0.01);
        $this->assertEqualsWithDelta($this->service->price + 100, $servicesB->pivot_price_override, 0.01);
    }

    public function test_resolver_parity_all_provider_services_in_query_all_services()
    {
        $resolver = new LocationServiceCatalogResolver();

        // Create multiple services for the provider
        $service2 = Service::factory()->create(['provider_id' => $this->provider->id, 'active' => true]);
        $service3 = Service::factory()->create(['provider_id' => $this->provider->id, 'active' => true]);

        $allServices = $resolver->queryAllServices($this->location)->get();

        $this->assertCount(3, $allServices);
        $this->assertContains($this->service->id, $allServices->pluck('id'));
        $this->assertContains($service2->id, $allServices->pluck('id'));
        $this->assertContains($service3->id, $allServices->pluck('id'));
    }

    public function test_resolver_parity_inactive_services_excluded_from_available()
    {
        $resolver = new LocationServiceCatalogResolver();

        // Create a service and explicitly disable it at this location
        $disabledService = Service::factory()->create(['provider_id' => $this->provider->id, 'active' => true]);

        \DB::table('location_service')->insert([
            'location_id' => $this->location->id,
            'service_id' => $disabledService->id,
            'is_available' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $availableServices = $resolver->queryAvailableServices($this->location)->get();

        // Service with is_available = false should be excluded
        $this->assertNotContains($disabledService->id, $availableServices->pluck('id'));
    }
}
