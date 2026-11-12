<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\SpecialistAssignment;
use App\Services\SessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionService = app(SessionService::class);
    }

    private function createConfirmedBookingWithSpecialist(): array
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'specialist_id' => $specialist->id,
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
            'specialist_id' => $specialist->id,
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
            'specialist' => $specialist,
            'provider' => $provider,
        ];
    }

    public function test_confirmed_booking_can_start_session()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $result = $this->sessionService->startSession($bookingService, $specialist);

        $this->assertEquals('in_progress', $result->session_status);
        $this->assertNotNull($result->started_at);
        $this->assertNull($result->actual_end_at);
    }

    public function test_pending_booking_cannot_start_session()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();
        
        $booking->update(['status' => 'pending']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Booking must be confirmed before starting a session');

        $this->sessionService->startSession($bookingService, $specialist);
    }

    public function test_wrong_specialist_cannot_start_session()
    {
        ['bookingService' => $bookingService, 'provider' => $provider] = $this->createConfirmedBookingWithSpecialist();
        
        $wrongSpecialist = Specialist::factory()->create();
        SpecialistAssignment::factory()->create([
            'specialist_id' => $wrongSpecialist->id,
            'provider_id' => $provider->id,
            'status' => 'ACTIVE',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You are not the assigned specialist for this booking');

        $this->sessionService->startSession($bookingService, $wrongSpecialist);
    }

    public function test_inactive_specialist_assignment_cannot_operate_session()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'specialist' => $specialist, 'provider' => $provider] = $this->createConfirmedBookingWithSpecialist();
        
        // Deactivate the assignment
        SpecialistAssignment::where('specialist_id', $specialist->id)
            ->where('provider_id', $provider->id)
            ->update(['status' => 'INACTIVE']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You do not have an active assignment at this provider');

        $this->sessionService->startSession($bookingService, $specialist);
    }

    public function test_duplicate_start_rejected()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        // First start
        $this->sessionService->startSession($bookingService, $specialist);

        // Second start should fail
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in scheduled state');

        $this->sessionService->startSession($bookingService, $specialist);
    }

    public function test_started_at_is_preserved()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $fixedTime = Carbon::parse('2026-12-01 10:07:00');
        Carbon::setTestNow($fixedTime);

        $result = $this->sessionService->startSession($bookingService, $specialist);

        $this->assertEquals($fixedTime, $result->started_at);

        // Try to overwrite started_at
        $this->expectException(ValidationException::class);
        $this->sessionService->startSession($bookingService, $specialist);

        Carbon::setTestNow();
    }

    public function test_late_scheduled_start_is_allowed()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        // Set current time to be after scheduled start
        $lateTime = Carbon::parse('2026-12-01 10:15:00');
        Carbon::setTestNow($lateTime);

        $result = $this->sessionService->startSession($bookingService, $specialist);

        $this->assertEquals('in_progress', $result->session_status);
        $this->assertEquals($lateTime, $result->started_at);

        Carbon::setTestNow();
    }

    public function test_session_can_complete_after_starting()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        // Start the session
        $this->sessionService->startSession($bookingService, $specialist);

        // Complete the session
        $result = $this->sessionService->completeSession($bookingService, $specialist);

        $this->assertEquals('completed', $result->session_status);
        $this->assertNotNull($result->actual_end_at);
    }

    public function test_completion_before_start_rejected()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in progress');

        $this->sessionService->completeSession($bookingService, $specialist);
    }

    public function test_duplicate_completion_rejected()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        // Start and complete
        $this->sessionService->startSession($bookingService, $specialist);
        $this->sessionService->completeSession($bookingService, $specialist);

        // Try to complete again
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in progress');

        $this->sessionService->completeSession($bookingService, $specialist);
    }

    public function test_actual_end_at_is_preserved()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $this->sessionService->startSession($bookingService, $specialist);

        $fixedTime = Carbon::parse('2026-12-01 11:14:00');
        Carbon::setTestNow($fixedTime);

        $result = $this->sessionService->completeSession($bookingService, $specialist);

        $this->assertEquals($fixedTime, $result->actual_end_at);

        // Try to overwrite actual_end_at
        $this->expectException(ValidationException::class);
        $this->sessionService->completeSession($bookingService, $specialist);

        Carbon::setTestNow();
    }

    public function test_completing_session_does_not_change_parent_booking_status()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $initialBookingStatus = $booking->status;

        $this->sessionService->startSession($bookingService, $specialist);
        $this->sessionService->completeSession($bookingService, $specialist);

        $booking->refresh();

        $this->assertEquals($initialBookingStatus, $booking->status);
        $this->assertEquals('confirmed', $booking->status);
    }

    public function test_booking_with_null_specialist_id_cannot_start_session()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $booking->update(['specialist_id' => null]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Booking has no assigned specialist');

        $this->sessionService->startSession($bookingService, $specialist);
    }

    public function test_session_not_in_scheduled_state_cannot_start()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $bookingService->update(['session_status' => 'in_progress']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in scheduled state');

        $this->sessionService->startSession($bookingService, $specialist);
    }

    public function test_session_not_in_progress_cannot_complete()
    {
        ['bookingService' => $bookingService, 'specialist' => $specialist] = $this->createConfirmedBookingWithSpecialist();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in progress');

        $this->sessionService->completeSession($bookingService, $specialist);
    }
}
