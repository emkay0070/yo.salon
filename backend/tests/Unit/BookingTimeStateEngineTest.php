<?php

namespace Tests\Unit;

use App\Console\Commands\ProcessBookingTimeStates;
use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\Location;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingTimeStateEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set test timezone to Africa/Kampala (UTC+3)
        Carbon::setTestNow('2026-10-06 12:00:00', 'Africa/Kampala');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_upcoming_state_before_due_soon_threshold()
    {
        // scheduled = 14:00, now = 12:00 (2 hours before)
        // Should be upcoming
        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'upcoming',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('upcoming', $booking->operational_state);
    }

    public function test_due_soon_state_within_30_minutes()
    {
        // scheduled = 14:00, now = 13:30 (30 minutes before)
        // Should be due_soon
        Carbon::setTestNow('2026-10-06 13:30:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'upcoming',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('due_soon', $booking->operational_state);
        
        // Verify activity was created
        $activities = BookingActivity::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $activities);
        $this->assertEquals('operational_state_due_soon', $activities->first()->type);
    }

    public function test_grace_period_starts_at_scheduled_time()
    {
        // scheduled = 14:00, now = 14:00
        // Should be grace_period, started_at = 14:00, ends_at = 14:15
        Carbon::setTestNow('2026-10-06 14:00:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'due_soon',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('grace_period', $booking->operational_state);
        
        // CRITICAL: Grace period must be anchored to scheduled_at, not current time
        $this->assertEquals('2026-10-06 14:00:00', $booking->grace_period_started_at->toDateTimeString());
        $this->assertEquals('2026-10-06 14:15:00', $booking->grace_period_ends_at->toDateTimeString());
        
        // Verify activity was created
        $activities = BookingActivity::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $activities);
        $this->assertEquals('grace_period_started', $activities->first()->type);
    }

    public function test_scheduler_runs_late_grace_period_anchored_to_scheduled_time()
    {
        // scheduled = 14:00, now = 14:03 (scheduler runs 3 minutes late)
        // CRITICAL: grace_period_started_at must still be 14:00, NOT 14:03
        Carbon::setTestNow('2026-10-06 14:03:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'due_soon',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('grace_period', $booking->operational_state);
        
        // CRITICAL: Must be anchored to scheduled time (14:00), not current time (14:03)
        $this->assertEquals('2026-10-06 14:00:00', $booking->grace_period_started_at->toDateTimeString());
        $this->assertEquals('2026-10-06 14:15:00', $booking->grace_period_ends_at->toDateTimeString());
    }

    public function test_grace_period_expires()
    {
        // scheduled = 14:00, now = 14:15 (grace period ends)
        // Should be needs_resolution
        Carbon::setTestNow('2026-10-06 14:15:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'grace_period',
            'grace_period_started_at' => '2026-10-06 14:00:00',
            'grace_period_ends_at' => '2026-10-06 14:15:00',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('needs_resolution', $booking->operational_state);
        
        // Verify activity was created
        $activities = BookingActivity::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $activities);
        $this->assertEquals('grace_period_expired', $activities->first()->type);
    }

    public function test_repeated_scheduler_run_is_idempotent()
    {
        // Run the processor multiple times
        // Verify: same operational state, same grace timestamps, exactly one activity
        Carbon::setTestNow('2026-10-06 14:03:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'due_soon',
        ]);

        // First run
        Artisan::call('bookings:process-time-states');
        $booking->refresh();
        $firstState = $booking->operational_state;
        $firstStartedAt = $booking->grace_period_started_at?->toDateTimeString();
        $firstEndsAt = $booking->grace_period_ends_at?->toDateTimeString();
        $firstActivityCount = BookingActivity::where('booking_id', $booking->id)->count();

        // Second run (simulating scheduler running again)
        Artisan::call('bookings:process-time-states');
        $booking->refresh();
        $secondState = $booking->operational_state;
        $secondStartedAt = $booking->grace_period_started_at?->toDateTimeString();
        $secondEndsAt = $booking->grace_period_ends_at?->toDateTimeString();
        $secondActivityCount = BookingActivity::where('booking_id', $booking->id)->count();

        // Verify idempotency
        $this->assertEquals($firstState, $secondState);
        $this->assertEquals($firstStartedAt, $secondStartedAt);
        $this->assertEquals($firstEndsAt, $secondEndsAt);
        $this->assertEquals(1, $firstActivityCount);
        $this->assertEquals(1, $secondActivityCount); // No duplicate activity
    }

    public function test_terminal_booking_not_reopened()
    {
        // Verify completed/cancelled/no_show bookings are not reopened
        Carbon::setTestNow('2026-10-06 14:15:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        foreach (['completed', 'cancelled', 'no_show'] as $status) {
            $booking = Booking::factory()->for($provider)->for($location)->create([
                'status' => $status,
                'date' => '2026-10-06',
                'time' => '14:00',
                'operational_state' => 'upcoming',
            ]);

            Artisan::call('bookings:process-time-states');

            $booking->refresh();
            $this->assertEquals('upcoming', $booking->operational_state, "Status {$status} should not transition");
        }
    }

    public function test_already_resolved_remains_resolved()
    {
        // Verify operational_state = resolved remains resolved
        Carbon::setTestNow('2026-10-06 14:15:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'resolved',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('resolved', $booking->operational_state);
    }

    public function test_arrived_booking_not_moved_to_grace_period()
    {
        // Verify an already-arrived booking is not incorrectly moved
        Carbon::setTestNow('2026-10-06 14:15:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'status' => 'arrived',
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'upcoming',
            'arrived_at' => '2026-10-06 13:55:00',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('upcoming', $booking->operational_state);
    }

    public function test_waiting_booking_not_moved_to_grace_period()
    {
        // Verify a waiting booking is not incorrectly moved
        Carbon::setTestNow('2026-10-06 14:15:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'status' => 'waiting',
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'upcoming',
            'waiting_since' => '2026-10-06 14:00:00',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('upcoming', $booking->operational_state);
    }

    public function test_in_progress_booking_not_moved_to_grace_period()
    {
        // Verify an in_progress booking is not incorrectly moved
        Carbon::setTestNow('2026-10-06 14:15:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'status' => 'in_progress',
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'upcoming',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('upcoming', $booking->operational_state);
    }

    public function test_timezone_fallback_to_africa_kampala()
    {
        // Test with provider/location with Africa/Kampala timezone
        // Should use Africa/Kampala
        Carbon::setTestNow('2026-10-06 14:00:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'due_soon',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('grace_period', $booking->operational_state);
    }

    public function test_booking_near_midnight()
    {
        // Test booking near midnight to ensure timezone handling is correct
        // scheduled = 2026-10-06 23:45, now = 2026-10-06 23:30 (15 minutes before)
        // Should be due_soon (not grace_period yet)
        Carbon::setTestNow('2026-10-06 23:30:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'date' => '2026-10-06',
            'time' => '23:45',
            'operational_state' => 'upcoming',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('due_soon', $booking->operational_state);
    }

    public function test_pending_payment_booking_processed()
    {
        // Verify pending_payment bookings are also processed
        Carbon::setTestNow('2026-10-06 14:00:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'status' => 'pending_payment',
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'due_soon',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('grace_period', $booking->operational_state);
    }

    public function test_pending_booking_not_processed()
    {
        // Verify pending bookings are NOT processed (not yet confirmed)
        Carbon::setTestNow('2026-10-06 14:00:00', 'Africa/Kampala');

        $provider = Provider::factory()->create(['timezone' => 'Africa/Kampala']);
        $location = Location::factory()->create(['provider_id' => $provider->id, 'timezone' => 'Africa/Kampala']);
        
        $booking = Booking::factory()->for($provider)->for($location)->create([
            'status' => 'pending',
            'date' => '2026-10-06',
            'time' => '14:00',
            'operational_state' => 'upcoming',
        ]);

        Artisan::call('bookings:process-time-states');

        $booking->refresh();
        $this->assertEquals('upcoming', $booking->operational_state);
    }
}
