<?php

namespace Tests\Unit;

use App\Models\Resource;
use App\Models\ResourceCapability;
use App\Models\ResourceException;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_belongs_to_location()
    {
        $provider = \App\Models\Provider::factory()->create();
        $location = \App\Models\Location::factory()->create(['provider_id' => $provider->id]);
        $resource = Resource::factory()->create(['location_id' => $location->id]);

        $this->assertInstanceOf(\App\Models\Location::class, $resource->location);
        $this->assertEquals($location->id, $resource->location->id);
    }

    public function test_resource_has_many_capabilities()
    {
        $resource = Resource::factory()->create();
        $capability1 = ResourceCapability::factory()->create(['resource_id' => $resource->id]);
        $capability2 = ResourceCapability::factory()->create(['resource_id' => $resource->id]);

        $this->assertCount(2, $resource->capabilities);
        $this->assertTrue($resource->capabilities->contains($capability1));
        $this->assertTrue($resource->capabilities->contains($capability2));
    }

    public function test_resource_has_many_exceptions()
    {
        $resource = Resource::factory()->create();
        $exception1 = ResourceException::factory()->create(['resource_id' => $resource->id]);
        $exception2 = ResourceException::factory()->create(['resource_id' => $resource->id]);

        $this->assertCount(2, $resource->exceptions);
        $this->assertTrue($resource->exceptions->contains($exception1));
        $this->assertTrue($resource->exceptions->contains($exception2));
    }

    public function test_resource_casts_attributes_correctly()
    {
        $resource = Resource::factory()->create([
            'capacity' => 2,
            'turnaround_time' => 10,
            'is_active' => true,
            'metadata' => ['key' => 'value'],
        ]);

        $this->assertIsInt($resource->capacity);
        $this->assertIsInt($resource->turnaround_time);
        $this->assertIsBool($resource->is_active);
        $this->assertIsArray($resource->metadata);
        $this->assertEquals(['key' => 'value'], $resource->metadata);
    }
}
