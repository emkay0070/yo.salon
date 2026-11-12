<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Events\PaymentConfirmed;
use App\Events\RefundConfirmed;
use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Provider;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ListenerDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_created_event_creates_one_activity_and_two_notifications()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $booking = Booking::factory()->for($provider, 'provider')->for($customer, 'customer')->create();
        
        $event = new BookingCreated(
            bookingId: $booking->id,
            customerId: $customer->id,
            providerId: $provider->id,
            customerName: 'Test Customer',
            serviceNames: 'Haircut',
            date: '2026-09-24',
            time: '10:00:00'
        );

        event($event);

        $this->assertCount(1, BookingActivity::all(), 'Exactly one BookingActivity should be created for a booking');
        $this->assertCount(2, Notification::all(), 'Exactly two notifications (provider, customer) should be created for a booking');
    }

    public function test_payment_confirmed_event_creates_one_activity_and_two_notifications()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $booking = Booking::factory()->for($provider, 'provider')->for($customer, 'customer')->create();
        
        $transaction = Transaction::factory()->create([
            'provider_id' => $provider->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'type' => 'payment',
            'gross_amount' => 100000,
            'status' => 'captured',
        ]);

        $event = new PaymentConfirmed(
            booking: $booking,
            transaction: $transaction,
            customerId: $customer->id
        );

        event($event);

        $this->assertCount(1, BookingActivity::all(), 'Exactly one BookingActivity should be created for a payment');
        $this->assertCount(2, Notification::all(), 'Exactly two notifications (provider, customer) should be created for a payment');
    }

    public function test_refund_confirmed_event_creates_two_notifications_and_no_extra_activity()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $booking = Booking::factory()->for($provider, 'provider')->for($customer, 'customer')->create();
        
        $originalTx = Transaction::factory()->create([
            'provider_id' => $provider->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'type' => 'payment',
            'status' => 'captured',
        ]);
        
        $refundTx = Transaction::factory()->create([
            'provider_id' => $provider->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'type' => 'refund',
            'status' => 'captured',
        ]);

        $event = new RefundConfirmed(
            originalTransaction: $originalTx,
            refundTransaction: $refundTx,
            booking: $booking,
            customerId: $customer->id,
            refundAmount: 50000,
            currency: 'UGX'
        );

        event($event);

        $this->assertCount(2, Notification::all(), 'Exactly two notifications (provider, customer) should be created for a refund');
        $this->assertCount(0, BookingActivity::all(), 'No booking activity is configured for refunds currently');
    }
}
