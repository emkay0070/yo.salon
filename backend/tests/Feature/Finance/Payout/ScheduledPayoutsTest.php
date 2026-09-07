<?php

namespace Tests\Feature\Finance\Payout;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\User;
use App\Models\Salon;
use App\Models\PaymentMethod;
use App\Models\PaymentProfile;
use App\Jobs\ProcessScheduledPayouts;
use App\Jobs\ExecutePayoutJob;
use Illuminate\Support\Facades\Queue;

class ScheduledPayoutsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_it_dispatches_execute_job_for_eligible_settlements()
    {
        $salon = Salon::factory()->create();
        $user = User::factory()->create();

        // 1. Valid Salon config for MTN
        PaymentMethod::create([
            'salon_id' => $salon->id,
            'display_name' => 'MTN Mobile Money',
            'provider' => 'mtn_disbursement',
            'is_active' => true,
            'api_subscription_key' => 'test-sub-key',
            'api_key' => 'test-api-key',
            'merchant_id' => 'test-merchant',
            'environment' => 'sandbox',
        ]);

        // 2. Valid recipient profile for MTN
        PaymentProfile::create([
            'owner_type' => get_class($user),
            'owner_id' => $user->id,
            'method' => 'mtn',
            'provider' => 'mtn_collection',
            'phone_number' => '0770000000',
            'network' => 'mtn',
            'is_verified' => true,
        ]);

        // 3. Due Settlement
        $settlement = Settlement::create([
            'payable_type' => get_class($salon),
            'payable_id' => $salon->id,
            'recipient_type' => get_class($user),
            'recipient_id' => $user->id,
            'amount' => 500.00,
            'currency' => 'UGX',
            'status' => 'pending',
            'scheduled_for' => now()->subDay(),
        ]);

        // Run Sweeper
        $sweeper = new ProcessScheduledPayouts();
        $sweeper->handle(app(\App\Services\Payments\PayoutReadinessService::class));

        // Assert job was dispatched
        Queue::assertPushed(ExecutePayoutJob::class, function ($job) use ($settlement) {
            return $job->settlementId == $settlement->id // loose: job stores string, settlement->id is int
                && $job->method === 'mtn';
        });
    }

    public function test_it_skips_settlements_with_active_payouts()
    {
        $salon = Salon::factory()->create();
        $user = User::factory()->create();

        PaymentMethod::create([
            'salon_id' => $salon->id,
            'display_name' => 'MTN Mobile Money',
            'provider' => 'mtn_disbursement',
            'is_active' => true,
            'api_subscription_key' => 'test-sub-key',
            'api_key' => 'test-api-key',
            'merchant_id' => 'test-merchant',
            'environment' => 'sandbox',
        ]);

        PaymentProfile::create([
            'owner_type' => get_class($user),
            'owner_id' => $user->id,
            'method' => 'mtn',
            'provider' => 'mtn_collection',
            'phone_number' => '0770000000',
            'network' => 'mtn',
            'is_verified' => true,
        ]);

        $settlement = Settlement::create([
            'payable_type' => get_class($salon),
            'payable_id' => $salon->id,
            'recipient_type' => get_class($user),
            'recipient_id' => $user->id,
            'amount' => 500.00,
            'currency' => 'UGX',
            'status' => 'pending',
            'scheduled_for' => now()->subDay(),
        ]);

        // Create an active payout (e.g. processing)
        $settlement->payouts()->create([
            'amount' => 500,
            'method' => 'mtn',
            'currency' => 'UGX',
            'status' => 'processing',
            'recipient_type' => get_class($user),
            'recipient_id' => $user->id,
            'execution_mode' => 'automated',
        ]);

        // Run Sweeper
        $sweeper = new ProcessScheduledPayouts();
        $sweeper->handle(app(\App\Services\Payments\PayoutReadinessService::class));

        // Assert job was NOT dispatched
        Queue::assertNotPushed(ExecutePayoutJob::class);
    }
}
