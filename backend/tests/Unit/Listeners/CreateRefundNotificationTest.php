<?php

namespace Tests\Unit\Listeners;

use App\Events\RefundConfirmed;
use App\Listeners\CreateRefundNotification;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Provider;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRefundNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_both_provider_and_customer_notifications_on_refund()
    {
        $provider = Provider::factory()->create();
        $customer = Customer::factory()->create();
        $booking = Booking::factory()->for($provider, 'provider')->for($customer, 'customer')->create();

        $originalTx = Transaction::factory()->create([
            'provider_id' => $provider->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'type' => 'payment',
            'gross_amount' => 100000,
            'net_amount' => 100000,
            'gateway_fee' => 0,
            'status' => 'captured',
        ]);
        $refundTx = Transaction::factory()->create([
            'provider_id' => $provider->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'type' => 'refund',
            'gross_amount' => -50000,
            'net_amount' => -50000,
            'gateway_fee' => 0,
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

        $this->assertSame(0, Notification::count());

        $listener = new CreateRefundNotification();
        $listener->handle($event);

        $this->assertSame(2, Notification::count(),
            'RefundConfirmed must fire exactly 2 notifications (provider + customer).');

        $providerNotif = Notification::whereNotNull('provider_id')
            ->where('provider_id', $provider->id)
            ->firstOrFail();
        $customerNotif = Notification::whereNotNull('customer_id')
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $sharedType = 'refund_confirmed';
        $this->assertSame($sharedType, $providerNotif->type);
        $this->assertSame($sharedType, $customerNotif->type);
        $this->assertSame('payment', $providerNotif->category);
        $this->assertSame('payment', $customerNotif->category);
        $this->assertSame('high', $providerNotif->priority);
        $this->assertSame('high', $customerNotif->priority);

        $this->assertSame($booking->id, $providerNotif->data['booking_id']);
        $this->assertSame($refundTx->id, $providerNotif->data['refund_transaction_id']);
        $this->assertSame($originalTx->id, $providerNotif->data['original_transaction_id']);
        $this->assertSame(50000, $providerNotif->data['amount']);
        $this->assertSame('UGX', $providerNotif->data['currency']);

        $this->assertSame($booking->id, $customerNotif->data['booking_id']);
        $this->assertSame($refundTx->id, $customerNotif->data['refund_transaction_id']);
        $this->assertSame(50000, $customerNotif->data['amount']);

        $this->assertStringContainsStringIgnoringCase('refund', $providerNotif->title);
        $this->assertStringContainsStringIgnoringCase('refund', $customerNotif->title);
        $this->assertStringContainsString('50,000', $providerNotif->message);
        $this->assertStringContainsString('50,000', $customerNotif->message);

        $this->assertStringContainsString('/dashboard/bookings/', $providerNotif->action_url);
        $this->assertStringContainsString('/portal/bookings/', $customerNotif->action_url);
    }
}
