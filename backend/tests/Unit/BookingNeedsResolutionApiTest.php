<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingNeedsResolutionApiTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location;
    private Location $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->otherLocation = Location::factory()->create(['provider_id' => $this->provider->id]);
    }

    public function test_needs_resolution_booking_is_returned()
    {
        $booking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::today()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals($booking->id, $data['bookings'][0]['id']);
        $this->assertEquals('needs_resolution', $data['bookings'][0]['operational_state']);
    }

    public function test_yesterdays_unresolved_booking_is_returned()
    {
        $booking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals($booking->id, $data['bookings'][0]['id']);
    }

    public function test_older_unresolved_booking_is_returned()
    {
        $booking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::now()->subDays(5)->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals($booking->id, $data['bookings'][0]['id']);
    }

    public function test_resolved_booking_is_not_returned()
    {
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'no_show',
            'operational_state' => 'resolved',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(0, $data['count']);
        $this->assertEmpty($data['bookings']);
    }

    public function test_upcoming_booking_is_not_returned()
    {
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(0, $data['count']);
        $this->assertEmpty($data['bookings']);
    }

    public function test_grace_period_booking_is_not_returned()
    {
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'grace_period',
            'date' => Carbon::today()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(0, $data['count']);
        $this->assertEmpty($data['bookings']);
    }

    public function test_location_scoped_user_cannot_see_other_location_bookings()
    {
        // Create booking in other location
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->otherLocation->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        // Query with first location context
        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(0, $data['count']);
    }

    public function test_provider_wide_user_can_see_all_locations()
    {
        // Create bookings in both locations
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->otherLocation->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '15:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        // Query with provider-wide scope (location_scope = 'all')
        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', null);
        $request->attributes->set('location_scope', 'all');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(2, $data['count']);
    }

    public function test_api_exposes_operational_state()
    {
        $booking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'grace_period_started_at' => Carbon::yesterday()->setTime(14, 0),
            'grace_period_ends_at' => Carbon::yesterday()->setTime(14, 15),
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings/needs-resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->needsResolution($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('needs_resolution', $data['bookings'][0]['operational_state']);
        $this->assertNotNull($data['bookings'][0]['grace_period_started_at']);
        $this->assertNotNull($data['bookings'][0]['grace_period_ends_at']);
    }

    public function test_booking_index_filters_by_operational_state()
    {
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '14:00',
        ]);

        $needsResolutionBooking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings?operational_state=needs_resolution');
        $request->query->set('operational_state', 'needs_resolution');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->index($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals($needsResolutionBooking->id, $data[0]['id']);
    }

    public function test_booking_index_filters_by_multiple_operational_states()
    {
        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'upcoming',
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '14:00',
        ]);

        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'confirmed',
            'operational_state' => 'needs_resolution',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'no_show',
            'operational_state' => 'resolved',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '15:00',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings?operational_state=needs_resolution,resolved');
        $request->query->set('operational_state', 'needs_resolution,resolved');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->index($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
    }

    public function test_booking_show_includes_resolution()
    {
        $booking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'no_show',
            'operational_state' => 'resolved',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $resolution = \App\Models\BookingResolution::factory()->create([
            'booking_id' => $booking->id,
            'outcome' => 'NO_SHOW',
            'reason_code' => 'CUSTOMER_DID_NOT_ARRIVE',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $response = $controller->show($booking);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($resolution->id, $data['resolution']['id']);
        $this->assertEquals('NO_SHOW', $data['resolution']['outcome']);
    }

    public function test_booking_index_includes_resolution_relationship()
    {
        $booking = Booking::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'status' => 'no_show',
            'operational_state' => 'resolved',
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '14:00',
        ]);

        $resolution = \App\Models\BookingResolution::factory()->create([
            'booking_id' => $booking->id,
            'outcome' => 'NO_SHOW',
            'reason_code' => 'CUSTOMER_DID_NOT_ARRIVE',
        ]);

        $controller = new \App\Http\Controllers\Api\V1\BookingController(
            app(\App\Services\BookingService::class),
            app(\App\Services\NotificationService::class)
        );

        $request = \Illuminate\Http\Request::create('/api/v1/bookings');
        $request->attributes->set('provider_id', $this->provider->id);
        $request->attributes->set('location_id', $this->location->id);
        $request->attributes->set('location_scope', 'specific');

        $response = $controller->index($request);

        $this->assertEquals(200, $response->status());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($resolution->id, $data[0]['resolution']['id']);
    }
}

