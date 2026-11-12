<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationJob;
use App\Models\User;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\ProviderUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Provider $provider;
    protected Provider $otherProvider;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->provider = Provider::factory()->create();
        $this->otherProvider = Provider::factory()->create();
        $this->customer = Customer::factory()->create();

        // Associate user with provider
        ProviderUser::factory()->create([
            'user_id' => $this->user->id,
            'provider_id' => $this->provider->id,
            'role' => 'admin',
        ]);
    }

    // --- Ownership Tests ---

    public function test_notification_belongs_to_provider()
    {
        $notification = Notification::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->provider->id, $notification->provider_id);
        $this->assertEquals($this->provider->id, $notification->provider->id);
    }

    public function test_notification_belongs_to_user()
    {
        $notification = Notification::factory()->forUser($this->user)->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->user->id, $notification->user_id);
        $this->assertEquals($this->user->id, $notification->user->id);
    }

    public function test_notification_belongs_to_customer()
    {
        $notification = Notification::factory()->forCustomer($this->customer)->create();

        $this->assertEquals($this->customer->id, $notification->customer_id);
        $this->assertEquals($this->customer->id, $notification->customer->id);
    }

    // --- Provider Isolation Tests ---

    public function test_provider_isolation_via_scope()
    {
        // Create notifications for both providers
        Notification::factory()->count(5)->create(['provider_id' => $this->provider->id]);
        Notification::factory()->count(3)->create(['provider_id' => $this->otherProvider->id]);

        // Query should only return provider's notifications
        $notifications = Notification::where('provider_id', $this->provider->id)->get();

        $this->assertCount(5, $notifications);
        $notifications->each(function ($notification) {
            $this->assertEquals($this->provider->id, $notification->provider_id);
        });
    }

    // --- Read/Unread Lifecycle Tests ---

    public function test_notification_can_be_marked_as_read()
    {
        $notification = Notification::factory()->create(['read_at' => null]);

        $this->assertFalse($notification->isRead());

        $notification->markAsRead();

        $this->assertTrue($notification->isRead());
        $this->assertNotNull($notification->read_at);
    }

    public function test_unread_notifications_count()
    {
        Notification::factory()->count(3)->create(['read_at' => null]);
        Notification::factory()->count(2)->create(['read_at' => now()]);

        $unreadCount = Notification::whereNull('read_at')->count();

        $this->assertEquals(3, $unreadCount);
    }

    // --- Factory States Tests ---

    public function test_factory_booking_state()
    {
        $notification = Notification::factory()->booking()->create();

        $this->assertEquals('booking_created', $notification->type);
        $this->assertEquals('booking', $notification->category);
        $this->assertEquals('high', $notification->priority);
        $this->assertEquals('calendar', $notification->icon);
    }

    public function test_factory_payment_state()
    {
        $notification = Notification::factory()->payment()->create();

        $this->assertEquals('payment_confirmed', $notification->type);
        $this->assertEquals('payment', $notification->category);
        $this->assertEquals('high', $notification->priority);
        $this->assertEquals('wallet', $notification->icon);
    }

    public function test_factory_review_state()
    {
        $notification = Notification::factory()->review()->create();

        $this->assertEquals('review_created', $notification->type);
        $this->assertEquals('review', $notification->category);
        $this->assertEquals('star', $notification->icon);
    }

    public function test_factory_testimonial_state()
    {
        $notification = Notification::factory()->testimonial()->create();

        $this->assertEquals('testimonial_created', $notification->type);
        $this->assertEquals('testimonial', $notification->category);
        $this->assertEquals('quote', $notification->icon);
    }

    public function test_factory_read_state()
    {
        $notification = Notification::factory()->read()->create();

        $this->assertNotNull($notification->read_at);
        $this->assertTrue($notification->isRead());
    }

    public function test_factory_unread_state()
    {
        $notification = Notification::factory()->unread()->create();

        $this->assertNull($notification->read_at);
        $this->assertFalse($notification->isRead());
    }

    public function test_factory_high_priority_state()
    {
        $notification = Notification::factory()->highPriority()->create();

        $this->assertEquals('high', $notification->priority);
    }

    public function test_factory_urgent_state()
    {
        $notification = Notification::factory()->urgent()->create();

        $this->assertEquals('urgent', $notification->priority);
    }

    public function test_factory_low_priority_state()
    {
        $notification = Notification::factory()->lowPriority()->create();

        $this->assertEquals('low', $notification->priority);
    }

    // --- NotificationJob Tests ---

    public function test_notification_job_sms_state()
    {
        $job = NotificationJob::factory()->sms()->create();

        $this->assertEquals('sms', $job->type);
        $this->assertNull($job->subject);
    }

    public function test_notification_job_email_state()
    {
        $job = NotificationJob::factory()->email()->create();

        $this->assertEquals('email', $job->type);
        $this->assertNotNull($job->subject);
    }

    public function test_notification_job_pending_state()
    {
        $job = NotificationJob::factory()->pending()->create();

        $this->assertEquals('pending', $job->status);
        $this->assertEquals(0, $job->attempts);
        $this->assertNull($job->sent_at);
    }

    public function test_notification_job_sent_state()
    {
        $job = NotificationJob::factory()->sent()->create();

        $this->assertEquals('sent', $job->status);
        $this->assertNotNull($job->sent_at);
        $this->assertNull($job->error_message);
    }

    public function test_notification_job_failed_state()
    {
        $job = NotificationJob::factory()->failed()->create();

        $this->assertEquals('failed', $job->status);
        $this->assertGreaterThan(0, $job->attempts);
        $this->assertNotNull($job->error_message);
    }

    public function test_notification_job_scheduled_state()
    {
        $job = NotificationJob::factory()->scheduled()->create();

        $this->assertNotNull($job->scheduled_at);
        $this->assertGreaterThan(now(), $job->scheduled_at);
    }

    public function test_notification_job_scheduled_past_state()
    {
        $job = NotificationJob::factory()->scheduledPast()->create();

        $this->assertNotNull($job->scheduled_at);
        $this->assertLessThan(now(), $job->scheduled_at);
    }

    public function test_notification_job_for_booking_state()
    {
        $job = NotificationJob::factory()->forBooking()->create();

        $this->assertEquals('booking', $job->related_type);
        $this->assertNotNull($job->related_id);
    }

    public function test_notification_job_with_provider_data()
    {
        $job = NotificationJob::factory()->withProviderData()->create();

        $this->assertArrayHasKey('provider_id', $job->data);
        $this->assertNotNull($job->data['provider_id']);
    }

    public function test_notification_job_pending_scope()
    {
        NotificationJob::factory()->count(3)->pending()->create();
        NotificationJob::factory()->count(2)->sent()->create();
        NotificationJob::factory()->count(1)->failed()->create();

        $pendingJobs = NotificationJob::pending()->get();

        $this->assertCount(3, $pendingJobs);
    }

    public function test_notification_job_failed_scope()
    {
        NotificationJob::factory()->count(2)->failed()->create(['attempts' => 1]);
        NotificationJob::factory()->count(1)->failed()->create(['attempts' => 3]);
        NotificationJob::factory()->count(3)->sent()->create();

        $failedJobs = NotificationJob::failed()->get();

        $this->assertCount(2, $failedJobs);
    }

    public function test_notification_job_of_type_scope()
    {
        NotificationJob::factory()->count(3)->sms()->create();
        NotificationJob::factory()->count(2)->email()->create();

        $smsJobs = NotificationJob::ofType('sms')->get();

        $this->assertCount(3, $smsJobs);
    }

    public function test_notification_job_mark_as_sent()
    {
        $job = NotificationJob::factory()->pending()->create();

        $this->assertEquals('pending', $job->status);

        $job->markAsSent();

        $this->assertEquals('sent', $job->status);
        $this->assertNotNull($job->sent_at);
    }

    public function test_notification_job_mark_as_failed()
    {
        $job = NotificationJob::factory()->pending()->create();

        $this->assertEquals('pending', $job->status);
        $this->assertEquals(0, $job->attempts);

        $job->markAsFailed('Test error');

        $this->assertEquals('failed', $job->status);
        $this->assertEquals('Test error', $job->error_message);
        $this->assertEquals(1, $job->attempts);
    }

    public function test_notification_job_mark_as_queued()
    {
        $job = NotificationJob::factory()->pending()->create();

        $this->assertEquals('pending', $job->status);

        $job->markAsQueued();

        $this->assertEquals('queued', $job->status);
    }
}
