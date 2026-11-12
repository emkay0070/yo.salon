<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Location;
use App\Models\PaymentRequest;
use App\Models\PaymentMethod;
use App\Models\Provider;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PaymentExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;
    protected Location $location;
    protected Customer $customer;
    protected Service $service;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->customer = Customer::factory()->create();
        $this->service = Service::factory()->create(['provider_id' => $this->provider->id]);
        $this->paymentMethod = PaymentMethod::factory()->create([
            'provider_id' => $this->provider->id,
            'provider' => 'flutterwave',
            'type' => 'card'
        ]);
    }

    public function test_expired_payment_request_is_marked_expired(): void
    {
        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);
    }

    public function test_expired_payment_request_with_sent_status_is_marked_expired(): void
    {
        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'sent',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);
    }

    public function test_expired_payment_cancels_pending_payment_booking(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'service_id' => $this->service->id,
        ]);

        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'payment_status' => 'expired',
        ]);
    }

    public function test_expired_payment_cancels_reserved_booking_and_releases_slot_hold(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'reserved',
            'payment_status' => 'unpaid',
            'service_id' => $this->service->id,
            'slot_locked_until' => now()->addMinutes(10),
        ]);

        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'payment_status' => 'expired',
            'slot_locked_until' => null,
        ]);
    }

    public function test_expiry_is_idempotent(): void
    {
        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => null,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        // Run expiry twice
        Artisan::call('payments:expire-pending');
        Artisan::call('payments:expire-pending');

        // Should still be expired, not changed again
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);

        $paymentRequest->refresh();
        $this->assertNotNull($paymentRequest->completed_at);
    }

    public function test_paid_booking_is_protected_from_expiry(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_payment',
            'payment_status' => 'paid', // Already paid
            'service_id' => $this->service->id,
        ]);

        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        // Payment request is expired
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);

        // But booking is NOT cancelled because it's already paid
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending_payment', // Still pending_payment, not cancelled
            'payment_status' => 'paid',
        ]);
    }

    public function test_confirmed_booking_is_not_affected_by_expiry(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'confirmed', // Already confirmed
            'payment_status' => 'paid',
            'service_id' => $this->service->id,
        ]);

        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        // Payment request is expired
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);

        // Booking remains confirmed
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function test_not_expired_payment_request_is_not_processed(): void
    {
        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->addMinutes(10), // Not expired yet
            'requested_at' => now(),
        ]);

        Artisan::call('payments:expire-pending');

        // Should still be pending
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_paid_payment_request_is_not_expired(): void
    {
        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'paid', // Already paid
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        // Should still be paid (not expired)
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'paid',
        ]);
    }

    public function test_cancelled_booking_is_not_affected_by_expiry(): void
    {
        $booking = Booking::factory()->create([
            'location_id' => $this->location->id,
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
            'status' => 'cancelled', // Already cancelled
            'payment_status' => 'unpaid',
            'service_id' => $this->service->id,
        ]);

        $paymentRequest = PaymentRequest::create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'booking_id' => $booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->paymentMethod->id,
            'status' => 'pending',
            'amount' => 5000,
            'expires_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(10),
        ]);

        Artisan::call('payments:expire-pending');

        // Payment request is expired
        $this->assertDatabaseHas('payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'expired',
        ]);

        // Booking remains cancelled (status unchanged)
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }
}
