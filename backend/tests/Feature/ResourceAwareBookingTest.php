<?php

namespace Tests\Feature;

use App\Models\AssignmentSchedule;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\ResourceCapability;
use App\Models\ResourceRequirement;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Services\BookingOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResourceAwareBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_with_resource_requirements_creates_allocations()
    {
        // Setup: Create provider -> location -> service relationship
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0, // No notice period for testing
        ]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        // Set up schedules for availability
        $testDate = CarbonImmutable::now()->addDays(1); // Ensure it's clearly in the future
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        // Create resource requirement
        ResourceRequirement::factory()->create([
            'service_id' => $service->id,
            'resource_type' => 'room',
            'count' => 1,
        ]);

        // Create available resource
        $resource = Resource::factory()->create([
            'location_id' => $location->id,
            'type' => 'room',
            'is_active' => true,
            'turnaround_time' => 0,
        ]);

        // Create booking
        $orchestrator = app(BookingOrchestrator::class);
        $result = $orchestrator->reserveAndBook([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00:00',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
        ]);

        $booking = $result['booking'];

        // Verify booking created
        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals($location->id, $booking->location_id);
        $this->assertEquals($service->id, $booking->service_id);

        // Verify BookingService created with timing
        $bookingService = BookingService::where('booking_id', $booking->id)->first();
        $this->assertNotNull($bookingService);
        $this->assertEquals($service->id, $bookingService->service_id);
        $this->assertNotNull($bookingService->start_time);
        $this->assertNotNull($bookingService->end_time);

        // Verify ResourceAllocation created
        $allocation = ResourceAllocation::where('booking_id', $booking->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals($resource->id, $allocation->resource_id);
        $this->assertEquals($bookingService->id, $allocation->booking_service_id);
    }

    public function test_booking_without_resource_requirements_succeeds()
    {
        // Setup: Create provider -> location -> service relationship
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
        ]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        // Set up schedules for availability
        $testDate = CarbonImmutable::now()->addDays(1); // Ensure it's clearly in the future
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $provider->id,
            'day_of_week' => $testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $location->id,
            'day_of_week' => $testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignment->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        // No resource requirements created

        // Create booking
        $orchestrator = app(BookingOrchestrator::class);
        $result = $orchestrator->reserveAndBook([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00:00',
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1234567890',
            'customer_email' => 'test@example.com',
        ]);

        $booking = $result['booking'];

        // Verify booking created without resource allocations
        $this->assertInstanceOf(Booking::class, $booking);
        $allocations = ResourceAllocation::where('booking_id', $booking->id)->get();
        $this->assertCount(0, $allocations);
    }
}
