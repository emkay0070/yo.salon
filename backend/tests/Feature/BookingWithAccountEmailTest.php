<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerSpecialist;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingWithAccountEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_with_account_blank_email_succeeds()
    {
        // Setup: Create provider with location
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30, 'price' => 50000]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules
        $testDate = CarbonImmutable::now()->addDays(1);
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
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $payload = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '+256700123456',
            'customer_email' => null, // BLANK EMAIL
            'service_id' => [$service->id],
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00',
        ];

        $response = $this->postJson('/api/v1/bookings/with-account', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'booking' => ['id', 'customer_id', 'provider_id', 'status'],
                'customer' => ['id', 'name', 'phone', 'email'],
                'is_new_customer',
            ]);

        // Verify booking was created
        $bookingId = $response->json('booking.id');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'customer_id' => $response->json('customer.id'),
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        // Verify customer was created with null email
        $customerId = $response->json('customer.id');
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'name' => 'John Doe',
            'phone' => '+256700123456',
            'email' => null,
        ]);

        // Verify customer_specialist relationship was created
        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customerId,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 1,
        ]);

        // Verify customer_providers relationship was created (for Clients list visibility)
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $customerId,
            'provider_id' => $provider->id,
        ]);
    }

    public function test_booking_with_account_populated_email_succeeds()
    {
        // Setup: Create provider with location
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30, 'price' => 50000]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules
        $testDate = CarbonImmutable::now()->addDays(1);
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
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $payload = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Jane Smith',
            'customer_phone' => '+256700789012',
            'customer_email' => 'jane@example.com', // POPULATED EMAIL
            'service_id' => [$service->id],
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00',
        ];

        $response = $this->postJson('/api/v1/bookings/with-account', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'booking' => ['id', 'customer_id', 'provider_id', 'status'],
                'customer' => ['id', 'name', 'phone', 'email'],
                'is_new_customer',
            ]);

        // Verify booking was created
        $bookingId = $response->json('booking.id');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'customer_id' => $response->json('customer.id'),
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        // Verify customer was created with email
        $customerId = $response->json('customer.id');
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'name' => 'Jane Smith',
            'phone' => '+256700789012',
            'email' => 'jane@example.com',
        ]);

        // Verify customer_specialist relationship was created
        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customerId,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 1,
        ]);

        // Verify customer_providers relationship was created (for Clients list visibility)
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $customerId,
            'provider_id' => $provider->id,
        ]);
    }

    public function test_transaction_rollback_on_customer_specialist_failure()
    {
        // Setup: Create provider with location
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30, 'price' => 50000]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules
        $testDate = CarbonImmutable::now()->addDays(1);
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
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        // Mock the CustomerSpecialistService to throw an exception
        $this->mock(\App\Services\CustomerSpecialistService::class, function ($mock) {
            $mock->shouldReceive('trackRelationshipFromBooking')
                ->andThrow(new \RuntimeException('Simulated failure in relationship tracking'));
        });

        $payload = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+256700999999',
            'customer_email' => 'test@example.com',
            'service_id' => [$service->id],
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00',
        ];

        $response = $this->postJson('/api/v1/bookings/with-account', $payload);

        // Should return 422 error
        $response->assertStatus(422);

        // Verify NO booking was created (transaction rollback)
        $this->assertDatabaseMissing('bookings', [
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        // Verify NO customer was created (transaction rollback)
        $this->assertDatabaseMissing('customers', [
            'phone' => '+256700999999',
        ]);

        // Verify NO customer_specialist relationship was created (transaction rollback)
        $this->assertDatabaseMissing('customer_specialist', [
            'specialist_id' => $specialist->id,
        ]);
    }

    public function test_transaction_commit_on_success()
    {
        // Setup: Create provider with location
        $provider = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $location = Location::factory()->create(['provider_id' => $provider->id]);
        
        $specialist = Specialist::factory()->create();
        $assignment = SpecialistAssignment::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $service = Service::factory()->create(['provider_id' => $provider->id, 'duration' => 30, 'price' => 50000]);
        DB::table('location_service')->insert([
            'location_id' => $location->id,
            'service_id' => $service->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules
        $testDate = CarbonImmutable::now()->addDays(1);
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
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $payload = [
            'provider_id' => $provider->id,
            'location_id' => $location->id,
            'customer_name' => 'Success Customer',
            'customer_phone' => '+256700888888',
            'customer_email' => 'success@example.com',
            'service_id' => [$service->id],
            'specialist_id' => $specialist->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00',
        ];

        $response = $this->postJson('/api/v1/bookings/with-account', $payload);

        $response->assertStatus(201);

        $bookingId = $response->json('booking.id');
        $customerId = $response->json('customer.id');

        // Verify ALL records were committed (transaction success)
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'customer_id' => $customerId,
            'provider_id' => $provider->id,
            'specialist_id' => $specialist->id,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'name' => 'Success Customer',
            'phone' => '+256700888888',
            'email' => 'success@example.com',
        ]);

        $this->assertDatabaseHas('customer_specialist', [
            'customer_id' => $customerId,
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
            'total_bookings' => 1,
        ]);
    }

    public function test_customer_appears_in_correct_provider_clients_list()
    {
        // Setup: Create two providers
        $providerA = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);
        $providerB = Provider::factory()->create([
            'type' => 'salon',
            'timezone' => 'Africa/Kampala',
        ]);

        $locationA = Location::factory()->create(['provider_id' => $providerA->id]);
        $locationB = Location::factory()->create(['provider_id' => $providerB->id]);
        
        $specialistA = Specialist::factory()->create();
        $assignmentA = SpecialistAssignment::factory()->create([
            'location_id' => $locationA->id,
            'provider_id' => $providerA->id,
            'specialist_id' => $specialistA->id,
        ]);

        $serviceA = Service::factory()->create(['provider_id' => $providerA->id, 'duration' => 30, 'price' => 50000]);
        DB::table('location_service')->insert([
            'location_id' => $locationA->id,
            'service_id' => $serviceA->id,
            'is_available' => true,
            'online_booking_enabled' => true,
            'min_booking_notice_override' => 0,
        ]);

        // Set up schedules for provider A
        $testDate = CarbonImmutable::now()->addDays(1);
        DB::table('provider_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'provider_id' => $providerA->id,
            'day_of_week' => $testDate->dayOfWeek,
            'status' => 'ACTIVE',
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('location_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'location_id' => $locationA->id,
            'day_of_week' => $testDate->format('l'),
            'is_closed' => false,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
        ]);
        DB::table('assignment_schedules')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'assignment_id' => $assignmentA->id,
            'day_of_week' => $testDate->format('l'),
            'status' => 'ACTIVE',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        // Create booking for provider A
        $payload = [
            'provider_id' => $providerA->id,
            'location_id' => $locationA->id,
            'customer_name' => 'Isolated Customer',
            'customer_phone' => '+256700555666',
            'customer_email' => 'isolated@example.com',
            'service_id' => [$serviceA->id],
            'specialist_id' => $specialistA->id,
            'date' => $testDate->toDateString(),
            'time' => '10:00',
        ];

        $response = $this->postJson('/api/v1/bookings/with-account', $payload);
        $response->assertStatus(201);

        $customerId = $response->json('customer.id');

        // Verify database state: customer_providers relationship exists for Provider A
        $this->assertDatabaseHas('customer_providers', [
            'customer_id' => $customerId,
            'provider_id' => $providerA->id,
        ]);

        // Verify database state: customer_providers relationship does NOT exist for Provider B
        $this->assertDatabaseMissing('customer_providers', [
            'customer_id' => $customerId,
            'provider_id' => $providerB->id,
        ]);

        // Verify Customer::providers() relationship works correctly for Provider A
        $customer = Customer::find($customerId);
        $providersA = $customer->providers()->where('provider_id', $providerA->id)->get();
        $this->assertCount(1, $providersA, 'Customer should have relationship with Provider A');

        // Verify Customer::providers() relationship does NOT include Provider B
        $providersB = $customer->providers()->where('provider_id', $providerB->id)->get();
        $this->assertCount(0, $providersB, 'Customer should NOT have relationship with Provider B');
    }
}
