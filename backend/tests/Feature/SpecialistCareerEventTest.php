<?php

namespace Tests\Feature;

use App\Models\SpecialistCareerEvent;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Provider;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpecialistCareerEventTest extends TestCase
{
    use RefreshDatabase;

    private Specialist $specialist;
    private Provider $provider;
    private Location $location;
    private SpecialistAssignment $assignment;

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

        // Create specialist
        $this->specialist = Specialist::create([
            'id' => Str::uuid(),
            'name' => 'Test Specialist',
            'active' => true,
            'provider_id' => $this->provider->id,
        ]);

        // Create assignment
        $this->assignment = SpecialistAssignment::create([
            'id' => Str::uuid(),
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'active',
        ]);
    }

    public function test_career_event_creation_with_canonical_fields()
    {
        $event = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'achievement',
            'title' => 'Test Achievement',
            'description' => 'Test description',
            'date' => '2026-01-15',
            'is_public' => true,
        ]);

        $this->assertNotNull($event);
        $this->assertEquals($this->specialist->id, $event->specialist_id);
        $this->assertEquals('achievement', $event->type);
        $this->assertEquals('Test Achievement', $event->title);
    }

    public function test_career_event_creation_with_assignment()
    {
        $event = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'employment',
            'title' => 'Test Employment',
            'date' => '2026-01-15',
            'assignment_id' => $this->assignment->id,
            'data' => ['role' => 'Senior Specialist'],
        ]);

        $this->assertNotNull($event);
        $this->assertEquals($this->assignment->id, $event->assignment_id);
        $this->assertEquals('employment', $event->type);
    }

    public function test_career_event_creation_without_assignment()
    {
        $event = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'certification',
            'title' => 'Test Certification',
            'date' => '2026-01-15',
            'assignment_id' => null,
        ]);

        $this->assertNotNull($event);
        $this->assertNull($event->assignment_id);
    }

    public function test_specialist_scoping()
    {
        $event = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'milestone',
            'title' => 'Test Milestone',
            'date' => '2026-01-15',
        ]);

        // Query by specialist should find it
        $found = SpecialistCareerEvent::forSpecialist($this->specialist->id)
            ->where('id', $event->id)
            ->exists();
        $this->assertTrue($found);

        // Create another specialist
        $otherSpecialist = Specialist::create([
            'id' => Str::uuid(),
            'name' => 'Other Specialist',
            'active' => true,
            'provider_id' => $this->provider->id,
        ]);

        // Query by other specialist should NOT find it
        $notFound = SpecialistCareerEvent::forSpecialist($otherSpecialist->id)
            ->where('id', $event->id)
            ->exists();
        $this->assertFalse($notFound);
    }

    public function test_public_visibility_scoping()
    {
        $publicEvent = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'achievement',
            'title' => 'Public Achievement',
            'date' => '2026-01-15',
            'is_public' => true,
        ]);

        $privateEvent = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'achievement',
            'title' => 'Private Achievement',
            'date' => '2026-01-15',
            'is_public' => false,
        ]);

        // Public scope should find public event
        $foundPublic = SpecialistCareerEvent::public()
            ->where('id', $publicEvent->id)
            ->exists();
        $this->assertTrue($foundPublic);

        // Public scope should NOT find private event
        $notFoundPrivate = SpecialistCareerEvent::public()
            ->where('id', $privateEvent->id)
            ->exists();
        $this->assertFalse($notFoundPrivate);
    }

    public function test_type_scoping()
    {
        $achievementEvent = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'achievement',
            'title' => 'Test Achievement',
            'date' => '2026-01-15',
        ]);

        $employmentEvent = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'employment',
            'title' => 'Test Employment',
            'date' => '2026-01-15',
        ]);

        // By type should find achievement
        $found = SpecialistCareerEvent::byType('achievement')
            ->where('id', $achievementEvent->id)
            ->exists();
        $this->assertTrue($found);

        // By type should NOT find employment
        $notFound = SpecialistCareerEvent::byType('achievement')
            ->where('id', $employmentEvent->id)
            ->exists();
        $this->assertFalse($notFound);
    }

    public function test_no_salon_id_in_fillable()
    {
        $this->assertNotContains('salon_id', (new SpecialistCareerEvent())->getFillable());
    }

    public function test_no_salon_relationship()
    {
        $event = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'milestone',
            'title' => 'Test Milestone',
            'date' => '2026-01-15',
        ]);

        // salon relationship should not exist
        $this->assertFalse(method_exists($event, 'salon'));
    }

    public function test_assignment_relationship_exists()
    {
        $event = SpecialistCareerEvent::create([
            'specialist_id' => $this->specialist->id,
            'type' => 'employment',
            'title' => 'Test Employment',
            'date' => '2026-01-15',
            'assignment_id' => $this->assignment->id,
        ]);

        $this->assertTrue(method_exists($event, 'assignment'));
        $this->assertEquals($this->assignment->id, $event->assignment->id);
    }
}
