<?php

namespace Tests\Unit;

use App\Models\Resource;
use App\Models\ResourceCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_belongs_to_resource()
    {
        $resource = Resource::factory()->create();
        $capability = ResourceCapability::factory()->create(['resource_id' => $resource->id]);

        $this->assertInstanceOf(Resource::class, $capability->resource);
        $this->assertEquals($resource->id, $capability->resource->id);
    }

    public function test_all_capabilities_required_semantic()
    {
        $resource = Resource::factory()->create();
        
        // Resource has both 'massage' and 'facial' capabilities
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'massage']);
        ResourceCapability::factory()->create(['resource_id' => $resource->id, 'capability' => 'facial']);

        // Query for resources that have BOTH capabilities
        $resources = Resource::where('id', $resource->id)
            ->whereHas('capabilities', function ($query) {
                $query->whereIn('capability', ['massage', 'facial'])
                    ->groupBy('resource_id')
                    ->havingRaw('COUNT(DISTINCT capability) = ?', [2]);
            })
            ->get();

        $this->assertCount(1, $resources);
        $this->assertEquals($resource->id, $resources->first()->id);
    }

    public function test_partial_capabilities_not_eligible()
    {
        $resource1 = Resource::factory()->create();
        $resource2 = Resource::factory()->create();
        
        // Resource 1 has both capabilities
        ResourceCapability::factory()->create(['resource_id' => $resource1->id, 'capability' => 'massage']);
        ResourceCapability::factory()->create(['resource_id' => $resource1->id, 'capability' => 'facial']);

        // Resource 2 has only one capability
        ResourceCapability::factory()->create(['resource_id' => $resource2->id, 'capability' => 'massage']);

        // Query for resources that have BOTH capabilities
        $resources = Resource::whereIn('id', [$resource1->id, $resource2->id])
            ->whereHas('capabilities', function ($query) {
                $query->whereIn('capability', ['massage', 'facial'])
                    ->groupBy('resource_id')
                    ->havingRaw('COUNT(DISTINCT capability) = ?', [2]);
            })
            ->get();

        $this->assertCount(1, $resources);
        $this->assertEquals($resource1->id, $resources->first()->id);
    }

    public function test_no_capabilities_required_skips_filter()
    {
        $resource = Resource::factory()->create();

        // Query without capability filter should return the resource
        $resources = Resource::where('id', $resource->id)->get();

        $this->assertCount(1, $resources);
        $this->assertEquals($resource->id, $resources->first()->id);
    }
}
