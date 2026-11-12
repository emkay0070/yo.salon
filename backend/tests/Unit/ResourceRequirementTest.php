<?php

namespace Tests\Unit;

use App\Models\ResourceRequirement;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_requirement_belongs_to_service()
    {
        $service = Service::factory()->create();
        $requirement = ResourceRequirement::factory()->create(['service_id' => $service->id]);

        $this->assertInstanceOf(Service::class, $requirement->service);
        $this->assertEquals($service->id, $requirement->service->id);
    }

    public function test_count_semantic_single_resource()
    {
        $requirement = ResourceRequirement::factory()->create(['count' => 1]);

        $this->assertEquals(1, $requirement->count);
    }

    public function test_count_semantic_multiple_resources()
    {
        $requirement = ResourceRequirement::factory()->create(['count' => 2]);

        $this->assertEquals(2, $requirement->count);
    }

    public function test_required_capabilities_casts_to_array()
    {
        $requirement = ResourceRequirement::factory()->create([
            'required_capabilities' => ['massage', 'hot_stone'],
        ]);

        $this->assertIsArray($requirement->required_capabilities);
        $this->assertEquals(['massage', 'hot_stone'], $requirement->required_capabilities);
    }

    public function test_min_capacity_nullable()
    {
        $requirement = ResourceRequirement::factory()->create(['min_capacity' => null]);

        $this->assertNull($requirement->min_capacity);
    }

    public function test_min_capacity_casts_to_integer()
    {
        $requirement = ResourceRequirement::factory()->create(['min_capacity' => 2]);

        $this->assertIsInt($requirement->min_capacity);
        $this->assertEquals(2, $requirement->min_capacity);
    }
}
