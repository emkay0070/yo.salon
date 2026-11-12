<?php

namespace Tests\Unit;

use App\Models\Resource;
use App\Models\ResourceCapability;
use App\Models\ResourceRequirement;
use App\Models\ResourceAllocation;
use App\Models\Service;
use App\Models\Location;
use App\Services\ResourceAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private ResourceAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->allocator = new ResourceAllocator();
    }

    public function test_allocate_resources_count_one()
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
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);

        $allocations = $this->allocator->allocateResources(
            serviceId: $service->id,
            locationId: $location->id,
            date: CarbonImmutable::parse('2026-12-01'),
            startTime: '10:00:00',
            endTime: '11:00:00',
        );

        $this->assertCount(1, $allocations);
        $this->assertEquals($resource->id, $allocations->first()['resource_id']);
    }

    public function test_allocate_resources_count_two_with_two_available()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 2,
        ]);

        $resource1 = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);
        $resource2 = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);

        $allocations = $this->allocator->allocateResources(
            serviceId: $service->id,
            locationId: $location->id,
            date: CarbonImmutable::parse('2026-12-01'),
            startTime: '10:00:00',
            endTime: '11:00:00',
        );

        $this->assertCount(2, $allocations);
        $resourceIds = $allocations->pluck('resource_id')->toArray();
        $this->assertContains($resource1->id, $resourceIds);
        $this->assertContains($resource2->id, $resourceIds);
    }

    public function test_allocate_resources_count_two_with_only_one_available_throws_exception()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 2,
        ]);

        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient resources');

        $this->allocator->allocateResources(
            serviceId: $service->id,
            locationId: $location->id,
            date: CarbonImmutable::parse('2026-12-03'),
            startTime: '10:00:00',
            endTime: '11:00:00',
        );
    }

    public function test_allocate_resources_deterministic_selection()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 2,
        ]);

        // Create resources with different IDs
        $resource1 = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);
        $resource2 = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);
        $resource3 = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);

        // Run allocation twice with different time slots to verify determinism
        $allocations1 = $this->allocator->allocateResources(
            serviceId: $service->id,
            locationId: $location->id,
            date: CarbonImmutable::parse('2026-12-01'),
            startTime: '10:00:00',
            endTime: '11:00:00',
        );

        $allocations2 = $this->allocator->allocateResources(
            serviceId: $service->id,
            locationId: $location->id,
            date: CarbonImmutable::parse('2026-12-02'),
            startTime: '14:00:00',
            endTime: '15:00:00',
        );

        $this->assertCount(2, $allocations1);
        $this->assertCount(2, $allocations2);
        
        // Both allocations should return the same resources (deterministic)
        $ids1 = $allocations1->pluck('resource_id')->sort()->toArray();
        $ids2 = $allocations2->pluck('resource_id')->sort()->toArray();
        $this->assertEquals($ids1, $ids2);
    }

    public function test_allocate_resources_with_capability_filter()
    {
        $location = Location::factory()->create();
        $service = Service::factory()->create();
        
        $requirement = ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'required_capabilities' => ['massage', 'hot_stone'],
            'count' => 1,
            'min_capacity' => 1,
        ]);

        // Resource with both capabilities
        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'capacity' => 1,
            'turnaround_time' => 0,
        ]);
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'massage']);
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'hot_stone']);

        $allocations = $this->allocator->allocateResources(
            serviceId: $service->id,
            locationId: $location->id,
            date: CarbonImmutable::now()->addDay(),
            startTime: '10:00:00',
            endTime: '11:00:00',
        );

        $this->assertCount(1, $allocations);
        $this->assertEquals($resource->id, $allocations->first()['resource_id']);
    }
}


