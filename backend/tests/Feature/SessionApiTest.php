<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\SpecialistAssignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionApiTest extends TestCase
{
    use RefreshDatabase;

    private $specialistAccount;
    private $specialist;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specialist = Specialist::factory()->create();
        $this->specialistAccount = SpecialistAccount::create([
            'specialist_id' => $this->specialist->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function createBookingWithSession(): array
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);

        SpecialistAssignment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'status' => 'ACTIVE',
        ]);

        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 60]);

        $booking = Booking::create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'customer_id' => $customer->id,
            'specialist_id' => $this->specialist->id,
            'service_id' => $service->id,
            'date' => '2026-12-01',
            'time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        $bookingService = BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'start_time' => Carbon::parse('2026-12-01 10:00:00'),
            'end_time' => Carbon::parse('2026-12-01 11:00:00'),
            'session_status' => 'scheduled',
        ]);

        return [
            'booking' => $booking,
            'bookingService' => $bookingService,
            'location' => $location,
            'service' => $service,
        ];
    }

    public function test_start_response_contains_session_state_fields()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'session_status',
            'started_at',
            'expected_end_at',
            'overrun',
        ]);

        $data = $response->json();
        $this->assertEquals('in_progress', $data['session_status']);
        $this->assertNotNull($data['started_at']);
        $this->assertNotNull($data['expected_end_at']);
        $this->assertNull($data['overrun']); // No overrun immediately after start
    }

    public function test_expected_end_at_uses_base_service_duration()
    {
        ['bookingService' => $bookingService, 'service' => $service] = $this->createBookingWithSession();

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        $data = $response->json();
        $expectedEnd = Carbon::parse($data['expected_end_at']);
        $calculatedEnd = $startTime->copy()->addMinutes($service->duration);

        $this->assertEquals($calculatedEnd, $expectedEnd);

        Carbon::setTestNow();
    }

    public function test_expected_end_at_respects_location_duration_override()
    {
        ['bookingService' => $bookingService, 'location' => $location, 'service' => $service] = $this->createBookingWithSession();

        // Insert location service override
        \DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'duration_override' => 90, // 90 minutes instead of base 60
        ]);

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        $data = $response->json();
        $expectedEnd = Carbon::parse($data['expected_end_at']);
        $calculatedEnd = $startTime->copy()->addMinutes(90); // Should use override

        $this->assertEquals($calculatedEnd, $expectedEnd);

        Carbon::setTestNow();
    }

    public function test_in_progress_before_expected_end_has_no_overrun()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        $data = $response->json();
        $this->assertNull($data['overrun']);

        Carbon::setTestNow();
    }

    public function test_in_progress_after_expected_end_exposes_live_overrun()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        // Advance time past expected end (60 minutes)
        Carbon::setTestNow($startTime->copy()->addMinutes(75));

        // Reload and check via resource
        $bookingService->refresh();
        $resource = new \App\Http\Resources\BookingServiceResource($bookingService->load(['booking', 'booking.location', 'service']));
        $data = $resource->toArray(request());

        // Overrun should be calculated based on current time vs expected end
        $this->assertNotNull($data['overrun']);
        $this->assertStringContainsString(':', $data['overrun']);

        Carbon::setTestNow();
    }

    public function test_completed_session_that_runs_late_exposes_overrun()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        // Complete 75 minutes after start (15 minutes late)
        Carbon::setTestNow($startTime->copy()->addMinutes(75));

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/complete");

        $data = $response->json();
        $this->assertEquals('completed', $data['session_status']);
        $this->assertNotNull($data['actual_end_at']);
        $this->assertNotNull($data['overrun']);

        Carbon::setTestNow();
    }

    public function test_completed_session_on_time_has_no_overrun()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        // Complete exactly at expected end (60 minutes)
        Carbon::setTestNow($startTime->copy()->addMinutes(60));

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/complete");

        $data = $response->json();
        $this->assertEquals('completed', $data['session_status']);
        $this->assertNull($data['overrun']);

        Carbon::setTestNow();
    }

    public function test_completed_session_early_has_no_overrun()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $startTime = Carbon::parse('2026-12-01 10:00:00');
        Carbon::setTestNow($startTime);

        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        // Complete early (45 minutes instead of 60)
        Carbon::setTestNow($startTime->copy()->addMinutes(45));

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/complete");

        $data = $response->json();
        $this->assertEquals('completed', $data['session_status']);
        $this->assertNull($data['overrun']);

        Carbon::setTestNow();
    }

    public function test_start_authorization_remains_unchanged()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        // Wrong specialist
        $wrongSpecialist = Specialist::factory()->create();
        $wrongAccount = SpecialistAccount::create([
            'specialist_id' => $wrongSpecialist->id,
            'email' => 'wrong@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($wrongAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You are not the assigned specialist for this booking.']);
    }

    public function test_complete_authorization_remains_unchanged()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        // Start with correct specialist
        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        // Try to complete with wrong specialist
        $wrongSpecialist = Specialist::factory()->create();
        $wrongAccount = SpecialistAccount::create([
            'specialist_id' => $wrongSpecialist->id,
            'email' => 'wrong2@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($wrongAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/complete");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You are not the assigned specialist for this booking.']);
    }

    public function test_cancel_scheduled_session()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/cancel");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('cancelled', $data['session_status']);
        $this->assertNull($data['expected_end_at']);
        $this->assertNull($data['overrun']);
    }

    public function test_cancel_in_progress_session()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        // Start the session
        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        // Cancel the in-progress session
        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/cancel");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('cancelled', $data['session_status']);
        $this->assertNull($data['expected_end_at']);
        $this->assertNull($data['overrun']);
    }

    public function test_cannot_cancel_completed_session()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        // Start and complete the session
        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/start");

        Carbon::setTestNow(Carbon::now()->addMinutes(60));

        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/complete");

        Carbon::setTestNow();

        // Try to cancel completed session
        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/cancel");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Session cannot be cancelled. Current status: completed']);
    }

    public function test_cannot_cancel_already_cancelled_session()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        // Cancel the session
        $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/cancel");

        // Try to cancel again
        $response = $this->actingAs($this->specialistAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/cancel");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Session cannot be cancelled. Current status: cancelled']);
    }

    public function test_cancel_authorization_remains_unchanged()
    {
        ['bookingService' => $bookingService] = $this->createBookingWithSession();

        // Wrong specialist
        $wrongSpecialist = Specialist::factory()->create();
        $wrongAccount = SpecialistAccount::create([
            'specialist_id' => $wrongSpecialist->id,
            'email' => 'wrong@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($wrongAccount, 'specialist')
            ->postJson("/api/v1/specialist-portal/sessions/{$bookingService->id}/cancel");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'You are not the assigned specialist for this booking.']);
    }
}
