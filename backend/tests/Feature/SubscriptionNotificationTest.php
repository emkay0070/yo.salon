<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Provider;
use App\Models\Notification;
use App\Events\SubscriptionRenewed;
use App\Events\SubscriptionPaymentFailed;
use App\Events\SubscriptionManualPaymentRequired;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;

class SubscriptionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since we are dispatching events manually and asserting their side effects, 
        // we shouldn't fake the events.
    }

    public function test_subscription_renewed_event_creates_notification()
    {
        $provider = Provider::factory()->create();
        $subscriptionId = Str::uuid()->toString();
        $invoiceId = Str::uuid()->toString();

        event(new SubscriptionRenewed($provider->id, $subscriptionId, $invoiceId));

        $notifications = Notification::where('provider_id', $provider->id)->get();
        
        $this->assertCount(1, $notifications, 'Appropriate event should not accidentally create duplicate notifications');
        
        $notification = $notifications->first();
        
        $this->assertEquals('subscription_renewed', $notification->type);
        $this->assertEquals('Your subscription has been successfully renewed. Thank you for continuing with Yo.Salon!', $notification->message);
        $this->assertEquals('/dashboard/billing', $notification->action_url);
        $this->assertEquals($subscriptionId, $notification->data['subscription_id']);
        $this->assertEquals($invoiceId, $notification->data['invoice_id']);
    }

    public function test_subscription_payment_failed_event_creates_notification()
    {
        $provider = Provider::factory()->create();
        $subscriptionId = Str::uuid()->toString();
        $invoiceId = Str::uuid()->toString();

        event(new SubscriptionPaymentFailed($provider->id, $subscriptionId, $invoiceId));

        $notifications = Notification::where('provider_id', $provider->id)->get();
        
        $this->assertCount(1, $notifications, 'Appropriate event should not accidentally create duplicate notifications');
        
        $notification = $notifications->first();
        
        $this->assertEquals('subscription_payment_failed', $notification->type);
        $this->assertEquals('Your recent subscription payment attempt failed. Please check your payment method.', $notification->message);
        $this->assertEquals('/dashboard/billing/invoices', $notification->action_url);
        $this->assertEquals($subscriptionId, $notification->data['subscription_id']);
        $this->assertEquals($invoiceId, $notification->data['invoice_id']);
    }

    public function test_subscription_manual_payment_required_event_creates_notification()
    {
        $provider = Provider::factory()->create();
        $subscriptionId = Str::uuid()->toString();
        $invoiceId = Str::uuid()->toString();

        event(new SubscriptionManualPaymentRequired($provider->id, $subscriptionId, $invoiceId));

        $notifications = Notification::where('provider_id', $provider->id)->get();
        
        $this->assertCount(1, $notifications, 'Appropriate event should not accidentally create duplicate notifications');
        
        $notification = $notifications->first();
        
        $this->assertEquals('subscription_manual_payment_required', $notification->type);
        $this->assertEquals('Action Required: Automatic collection failed. Please process your subscription payment manually to prevent service interruption.', $notification->message);
        $this->assertEquals('/dashboard/billing/payment', $notification->action_url);
        $this->assertEquals($subscriptionId, $notification->data['subscription_id']);
        $this->assertEquals($invoiceId, $notification->data['invoice_id']);
    }
}
