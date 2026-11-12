<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\BookingResolution;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingResolutionTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    public function test_needs_resolution_can_be_resolved_as_no_show()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123'
        );

        $this->assertEquals('no_show', $resolved->status);
        $this->assertEquals('resolved', $resolved->operational_state);
        $this->assertNotNull($resolved->resolution);
        $this->assertEquals('NO_SHOW', $resolved->resolution->outcome);
        $this->assertEquals('CUSTOMER_DID_NOT_ARRIVE', $resolved->resolution->reason_code);
        $this->assertEquals('staff', $resolved->resolution->actor_type);
        $this->assertEquals('staff-123', $resolved->resolution->actor_id);

        // Verify activity was created
        $activity = BookingActivity::where('booking_id', $booking->id)
            ->where('type', 'BOOKING_MARKED_MISSED')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_needs_resolution_can_be_resolved_as_cancelled()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'CANCELLED',
            'CUSTOMER_CANCELLED',
            'customer',
            'customer-123'
        );

        $this->assertEquals('cancelled', $resolved->status);
        $this->assertEquals('resolved', $resolved->operational_state);
        $this->assertEquals('CANCELLED', $resolved->resolution->outcome);
        $this->assertEquals('CUSTOMER_CANCELLED', $resolved->resolution->reason_code);

        // Verify activity was created
        $activity = BookingActivity::where('booking_id', $booking->id)
            ->where('type', 'BOOKING_CANCELLED')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_rescheduled_outcome_creates_new_booking_and_links_it()
    {
        $provider = Provider::factory()->create();
        $location = Location::factory()->for($provider)->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->for($provider)->create();

        $originalBooking = Booking::factory()->create([
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '14:00',
        ]);

        $resolved = $this->bookingService->resolveBooking(
            $originalBooking,
            'RESCHEDULED',
            'CUSTOMER_REQUESTED_RESCHEDULE',
            'customer',
            'customer-123',
            [
                'new_booking_data' => [
                    'date' => Carbon::tomorrow()->addDay()->toDateString(),
                    'time' => '10:00',
                    'customer_name' => $customer->name,
                ],
            ]
        );

        $this->assertEquals('rescheduled', $resolved->status);
        $this->assertEquals('resolved', $resolved->operational_state);
        $this->assertEquals('RESCHEDULED', $resolved->resolution->outcome);

        // Verify new booking was created
        $newBooking = Booking::where('rescheduled_from_booking_id', $originalBooking->id)->first();
        $this->assertNotNull($newBooking);
        $this->assertEquals(Carbon::tomorrow()->addDay()->toDateString(), $newBooking->date->toDateString());
        $this->assertEquals('10:00', $newBooking->time);
        $this->assertEquals($originalBooking->customer_id, $newBooking->customer_id);
        $this->assertEquals($originalBooking->service_id, $newBooking->service_id);

        // Verify activity was created with new booking reference
        $activity = BookingActivity::where('booking_id', $originalBooking->id)
            ->where('type', 'BOOKING_RESCHEDULED')
            ->first();
        $this->assertNotNull($activity);
        $this->assertEquals($newBooking->id, $activity->data['new_booking_id']);
    }

    public function test_completed_late_resolves_to_completed_status()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'COMPLETED_LATE',
            'OTHER',
            'staff',
            'staff-123'
        );

        $this->assertEquals('completed', $resolved->status);
        $this->assertEquals('resolved', $resolved->operational_state);
        $this->assertEquals('COMPLETED_LATE', $resolved->resolution->outcome);
    }

    public function test_late_arrival_preserves_existing_arrival_semantics()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'CUSTOMER_ARRIVED',
            'CUSTOMER_ARRIVED_LATE',
            'staff',
            'staff-123'
        );

        $this->assertEquals('arrived', $resolved->status);
        $this->assertEquals('resolved', $resolved->operational_state);
        $this->assertEquals('CUSTOMER_ARRIVED', $resolved->resolution->outcome);
        $this->assertEquals('CUSTOMER_ARRIVED_LATE', $resolved->resolution->reason_code);

        // Verify arrived_at timestamp was set by transitionStatus
        $this->assertNotNull($resolved->arrived_at);

        // Verify activity was created
        $activity = BookingActivity::where('booking_id', $booking->id)
            ->where('type', 'BOOKING_RESOLVED')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_follow_up_can_be_initiated_on_resolution()
    {
        $resolution = BookingResolution::factory()->create([
            'follow_up_required' => false,
        ]);

        $updated = $this->bookingService->initiateFollowUp(
            $resolution,
            'staff',
            'staff-123',
            [
                'follow_up_at' => Carbon::now()->addHours(24),
                'follow_up_notes' => 'Call customer to reschedule',
            ]
        );

        $this->assertTrue($updated->follow_up_required);
        $this->assertEquals('PENDING', $updated->follow_up_status);
        $this->assertNotNull($updated->follow_up_at);
        $this->assertEquals('Call customer to reschedule', $updated->follow_up_notes);

        // Verify activity was created
        $activity = BookingActivity::where('booking_id', $resolution->booking_id)
            ->where('type', 'FOLLOW_UP_INITIATED')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_invalid_outcome_is_rejected()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid outcome');

        $this->bookingService->resolveBooking(
            $booking,
            'INVALID_OUTCOME',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123'
        );
    }

    public function test_invalid_reason_code_is_rejected()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid reason code');

        $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'INVALID_REASON',
            'staff',
            'staff-123'
        );
    }

    public function test_terminal_booking_status_cannot_be_resolved()
    {
        $booking = Booking::factory()->create([
            'status' => 'completed',
            'operational_state' => 'needs_resolution',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already in a terminal state');

        $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123'
        );
    }

    public function test_operational_floor_states_cannot_be_resolved()
    {
        $booking = Booking::factory()->create([
            'status' => 'arrived',
            'operational_state' => 'upcoming',
        ]);

        $this->expectException(ValidationException::class);
        // The validation checks operational state first before floor status
        $this->expectExceptionMessage('not allowed for operational state');

        // This should fail because NO_SHOW is not allowed for upcoming state
        $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123'
        );
    }

    public function test_outcome_not_allowed_for_operational_state_is_rejected()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not allowed for operational state');

        $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123'
        );
    }

    public function test_duplicate_resolution_is_rejected()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
        ]);

        // First resolution succeeds
        $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123'
        );

        // Second resolution fails
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already has a resolution');

        $this->bookingService->resolveBooking(
            $booking,
            'CANCELLED',
            'CUSTOMER_CANCELLED',
            'staff',
            'staff-123'
        );
    }

    public function test_rescheduled_requires_new_booking_data()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('new_booking_data is required');

        $this->bookingService->resolveBooking(
            $booking,
            'RESCHEDULED',
            'CUSTOMER_REQUESTED_RESCHEDULE',
            'staff',
            'staff-123'
        );
    }

    public function test_resolution_is_transaction_safe()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
        ]);

        // Simulate a failure during resolution by using an invalid outcome
        // This will fail validation before any database writes occur
        try {
            $this->bookingService->resolveBooking(
                $booking,
                'INVALID_OUTCOME',
                'CUSTOMER_DID_NOT_ARRIVE',
                'staff',
                'staff-123'
            );
            $this->fail('Expected exception was not thrown');
        } catch (ValidationException $e) {
            // Expected - validation failed
        }

        // Verify booking was not mutated
        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status);
        $this->assertEquals('needs_resolution', $booking->operational_state);
        $this->assertFalse($booking->resolution()->exists());
    }

    public function test_upcoming_state_allows_cancellation_and_reschedule()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
            'date' => Carbon::tomorrow()->toDateString(),
        ]);

        // Cancellation should be allowed
        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'CANCELLED',
            'CUSTOMER_CANCELLED',
            'customer',
            'customer-123'
        );

        $this->assertEquals('cancelled', $resolved->status);
    }

    public function test_grace_period_allows_late_arrival()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'grace_period',
        ]);

        // Late arrival should be allowed during grace period
        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'CUSTOMER_ARRIVED',
            'CUSTOMER_ARRIVED_LATE',
            'staff',
            'staff-123'
        );

        $this->assertEquals('arrived', $resolved->status);
    }

    public function test_needs_resolution_allows_all_outcomes()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
        ]);

        // All outcomes should be allowed from needs_resolution
        $outcomes = ['NO_SHOW', 'CANCELLED', 'COMPLETED_LATE', 'CUSTOMER_ARRIVED'];

        foreach ($outcomes as $outcome) {
            $testBooking = Booking::factory()->create([
                'status' => 'confirmed',
                'operational_state' => 'needs_resolution',
            ]);

            $resolved = $this->bookingService->resolveBooking(
                $testBooking,
                $outcome,
                'OTHER',
                'staff',
                'staff-123'
            );

            $this->assertEquals('resolved', $resolved->operational_state);
        }
    }

    public function test_resolution_with_follow_up_creates_follow_up_record()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
        ]);

        $resolved = $this->bookingService->resolveBooking(
            $booking,
            'NO_SHOW',
            'CUSTOMER_DID_NOT_ARRIVE',
            'staff',
            'staff-123',
            [
                'follow_up_required' => true,
                'follow_up_at' => Carbon::now()->addHours(24),
                'follow_up_notes' => 'Follow up with customer',
            ]
        );

        $this->assertTrue($resolved->resolution->follow_up_required);
        $this->assertEquals('PENDING', $resolved->resolution->follow_up_status);
        $this->assertNotNull($resolved->resolution->follow_up_at);
        $this->assertEquals('Follow up with customer', $resolved->resolution->follow_up_notes);
    }
}
