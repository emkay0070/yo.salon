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
        $account = SpecialistAccount::create([
            'specialist_id' => $specialist->id,
            'email' => "specialist-{$specialist->id}@example.com",
            'password' => bcrypt('password'),
            'is_active' => true,
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
            'account' => $account,
            'provider' => $provider,
            'service' => $service,
        ];
    }

    public function test_confirmed_booking_can_start_session()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $result = $this->sessionService->startSession($bookingService, $account);

        $this->assertEquals('in_progress', $result->session_status);
        $this->assertNotNull($result->started_at);
        $this->assertNull($result->actual_end_at);
    }

    public function test_pending_booking_cannot_start_session()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();
        
        $booking->update(['status' => 'pending']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Booking must be confirmed, arrived, or waiting before starting a session');

        $this->sessionService->startSession($bookingService, $account);
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
        $wrongAccount = SpecialistAccount::create([
            'specialist_id' => $wrongSpecialist->id,
            'email' => "specialist-{$wrongSpecialist->id}@example.com",
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You are not the assigned specialist for this booking');

        $this->sessionService->startSession($bookingService, $wrongAccount);
    }

    public function test_inactive_specialist_assignment_cannot_operate_session()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'specialist' => $specialist, 'account' => $account, 'provider' => $provider] = $this->createConfirmedBookingWithSpecialist();
        
        // Deactivate the assignment
        SpecialistAssignment::where('specialist_id', $specialist->id)
            ->where('provider_id', $provider->id)
            ->update(['status' => 'INACTIVE']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The assigned specialist does not have an active assignment at this provider');

        $this->sessionService->startSession($bookingService, $account);
    }

    public function test_duplicate_start_rejected()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        // First start
        $this->sessionService->startSession($bookingService, $account);

        // Second start should fail: the parent booking is already underway.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Booking must be confirmed, arrived, or waiting before starting a session');

        $this->sessionService->startSession($bookingService, $account);
    }

    public function test_started_at_is_preserved()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $fixedTime = Carbon::parse('2026-12-01 10:07:00');
        Carbon::setTestNow($fixedTime);

        $result = $this->sessionService->startSession($bookingService, $account);

        $this->assertEquals($fixedTime, $result->started_at);

        // Try to overwrite started_at
        $this->expectException(ValidationException::class);
        $this->sessionService->startSession($bookingService, $account);

        Carbon::setTestNow();
    }

    public function test_late_scheduled_start_is_allowed()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        // Set current time to be after scheduled start
        $lateTime = Carbon::parse('2026-12-01 10:15:00');
        Carbon::setTestNow($lateTime);

        $result = $this->sessionService->startSession($bookingService, $account);

        $this->assertEquals('in_progress', $result->session_status);
        $this->assertEquals($lateTime, $result->started_at);

        Carbon::setTestNow();
    }

    public function test_session_can_complete_after_starting()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        // Start the session
        $this->sessionService->startSession($bookingService, $account);

        // Complete the session
        $result = $this->sessionService->completeSession($bookingService, $account);

        $this->assertEquals('completed', $result->session_status);
        $this->assertNotNull($result->actual_end_at);
    }

    public function test_completion_before_start_rejected()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in progress');

        $this->sessionService->completeSession($bookingService, $account);
    }

    public function test_duplicate_completion_rejected()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        // Start and complete
        $this->sessionService->startSession($bookingService, $account);
        $this->sessionService->completeSession($bookingService, $account);

        // Try to complete again
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in progress');

        $this->sessionService->completeSession($bookingService, $account);
    }

    public function test_actual_end_at_is_preserved()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $this->sessionService->startSession($bookingService, $account);

        $fixedTime = Carbon::parse('2026-12-01 11:14:00');
        Carbon::setTestNow($fixedTime);

        $result = $this->sessionService->completeSession($bookingService, $account);

        $this->assertEquals($fixedTime, $result->actual_end_at);

        // Try to overwrite actual_end_at
        $this->expectException(ValidationException::class);
        $this->sessionService->completeSession($bookingService, $account);

        Carbon::setTestNow();
    }

    public function test_completing_one_of_multiple_sessions_does_not_complete_parent_booking()
    {
        [
            'booking' => $booking,
            'bookingService' => $bookingService,
            'account' => $account,
            'service' => $service,
        ] = $this->createConfirmedBookingWithSpecialist();

        // Second service session for the same booking
        $secondSession = BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'start_time' => Carbon::parse('2026-12-01 11:00:00'),
            'end_time' => Carbon::parse('2026-12-01 12:00:00'),
            'session_status' => 'scheduled',
        ]);

        $this->sessionService->startSession($bookingService, $account);
        $this->sessionService->completeSession($bookingService, $account);

        $booking->refresh();

        // One of two sessions is complete: the parent booking is still underway.
        $this->assertEquals('in_progress', $booking->status);
        $this->assertNotEquals('completed', $booking->status);
        $this->assertSame('scheduled', $secondSession->fresh()->session_status);
    }

    public function test_booking_with_null_specialist_id_cannot_start_session()
    {
        ['booking' => $booking, 'bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $booking->update(['specialist_id' => null]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Booking has no assigned specialist');

        $this->sessionService->startSession($bookingService, $account);
    }

    public function test_session_not_in_scheduled_state_cannot_start()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $bookingService->update(['session_status' => 'in_progress']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in scheduled state');

        $this->sessionService->startSession($bookingService, $account);
    }

    public function test_session_not_in_progress_cannot_complete()
    {
        ['bookingService' => $bookingService, 'account' => $account] = $this->createConfirmedBookingWithSpecialist();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Session is not in progress');

        $this->sessionService->completeSession($bookingService, $account);
    }
}
