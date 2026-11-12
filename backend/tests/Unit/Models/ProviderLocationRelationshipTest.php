<?php

namespace Tests\Unit\Models;

use App\Models\Location;
use App\Models\Provider;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderLocationRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_owns_locations()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        $this->assertTrue($provider->locations->contains($location));
        $this->assertEquals($provider->id, $location->provider->id);
    }

    public function test_provider_and_location_own_specialist_assignments()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $specialist = Specialist::factory()->create();

        $assignment = SpecialistAssignment::factory()->create([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'specialist_id' => $specialist->id,
        ]);

        $this->assertTrue($provider->assignments->contains($assignment));
        $this->assertTrue($location->assignments->contains($assignment));
    }
}
