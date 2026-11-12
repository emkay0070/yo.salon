<?php

namespace Tests\Unit;

use App\Models\Resource;
use App\Models\ResourceCapability;
use App\Models\ResourceException;
use App\Models\ResourceRequirement;
use App\Models\Service;
use App\Models\Location;
use App\Services\ResourceAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResourceAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResourceAvailabilityService();
    }

    public function test_is_available_for_no_requirements_returns_available()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();

        $result = $this->service->isAvailableFor(
            serviceId: $service->id,
            locationId: $location->id,
            start: CarbonImmutable::now()->setTime(10, 0),
            end: CarbonImmutable::now()->setTime(11, 0),
        );

        $this->assertTrue($result->available);
        $this->assertEmpty($result->candidates);
    }

    public function test_is_available_for_with_eligible_resources()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        // Create requirement
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 1,
        ]);

        // Create eligible resource
        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
        ]);

        $result = $this->service->isAvailableFor(
            serviceId: $service->id,
            locationId: $location->id,
            start: CarbonImmutable::now()->setTime(10, 0),
            end: CarbonImmutable::now()->setTime(11, 0),
        );

        $this->assertTrue($result->available);
        $this->assertCount(1, $result->candidates);
    }

    public function test_is_available_for_insufficient_resources_returns_unavailable()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        // Create requirement needing 2 resources
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 2,
        ]);

        // Create only 1 eligible resource
        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
        ]);

        $result = $this->service->isAvailableFor(
            serviceId: $service->id,
            locationId: $location->id,
            start: CarbonImmutable::now()->setTime(10, 0),
            end: CarbonImmutable::now()->setTime(11, 0),
        );

        $this->assertFalse($result->available);
        $this->assertNotNull($result->reason);
    }

    public function test_is_available_for_with_exception_returns_unavailable()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 1,
        ]);

        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
        ]);

        // Create exception during requested time
        ResourceException::factory()->create([
            'resource_id' => $resource->id,
            'date' => CarbonImmutable::now()->toDateString(),
            'exception_type' => 'maintenance',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        $result = $this->service->isAvailableFor(
            serviceId: $service->id,
            locationId: $location->id,
            start: CarbonImmutable::now()->setTime(10, 0),
            end: CarbonImmutable::now()->setTime(11, 0),
        );

        $this->assertFalse($result->available);
    }

    public function test_is_available_for_with_all_capabilities_required()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'required_capabilities' => ['massage', 'hot_stone'],
            'count' => 1,
        ]);

        // Resource with both capabilities
        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
        ]);
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'massage']);
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'hot_stone']);

        $result = $this->service->isAvailableFor(
            serviceId: $service->id,
            locationId: $location->id,
            start: CarbonImmutable::now()->setTime(10, 0),
            end: CarbonImmutable::now()->setTime(11, 0),
        );

        $this->assertTrue($result->available);
    }

    public function test_is_available_for_with_partial_capabilities_returns_unavailable()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'required_capabilities' => ['massage', 'hot_stone'],
            'count' => 1,
        ]);

        // Resource with only one capability
        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
        ]);
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'massage']);

        $result = $this->service->isAvailableFor(
            serviceId: $service->id,
            locationId: $location->id,
            start: CarbonImmutable::now()->setTime(10, 0),
            end: CarbonImmutable::now()->setTime(11, 0),
        );

        $this->assertFalse($result->available);
    }
}











