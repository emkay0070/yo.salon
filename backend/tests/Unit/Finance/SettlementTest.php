<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Settlement\Settlement;
use App\Domain\Finance\Payout\Payout;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_paid_works_with_and_without_explicit_timestamp()
    {
        $provider = Provider::factory()->create();
        $user = User::factory()->create();
        
        $settlement1 = Settlement::create([
            'provider_id' => $provider->id,
            'payable_type' => 'App\Models\Provider',
            'payable_id' => $provider->id,
            'recipient_type' => 'App\Models\User',
            'recipient_id' => $user->id,
            'amount' => 10000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
            'reference_type' => 'App\Models\Booking',
            'reference_id' => Str::uuid(),
        ]);

        $settlement2 = Settlement::create([
            'provider_id' => $provider->id,
            'payable_type' => 'App\Models\Provider',
            'payable_id' => $provider->id,
            'recipient_type' => 'App\Models\User',
            'recipient_id' => $user->id,
            'amount' => 20000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
            'reference_type' => 'App\Models\Booking',
            'reference_id' => Str::uuid(),
        ]);

        // 1. Without explicit timestamp (should use now())
        $settlement1->markAsPaid();
        $this->assertEquals(Settlement::STATUS_PAID, $settlement1->status);
        $this->assertNotNull($settlement1->completed_at);
        $this->assertTrue($settlement1->completed_at->isToday());

        // 2. With explicit timestamp (used when Payout propagates its own processed_at)
        $pastDate = now()->subDays(2);
        $settlement2->markAsPaid($pastDate);
        $this->assertEquals(Settlement::STATUS_PAID, $settlement2->status);
        $this->assertEquals($pastDate->toDateTimeString(), $settlement2->completed_at->toDateTimeString());
    }

    public function test_payout_belongs_to_settlement_via_uuid()
    {
        $provider = Provider::factory()->create();
        $user = User::factory()->create();
        
        $settlement = Settlement::create([
            'provider_id' => $provider->id,
            'payable_type' => 'App\Models\Provider',
            'payable_id' => $provider->id,
            'recipient_type' => 'App\Models\User',
            'recipient_id' => $user->id,
            'amount' => 15000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
            'reference_type' => 'App\Models\Booking',
            'reference_id' => Str::uuid(),
        ]);

        // If the UUID type mismatch migration failed, this creation will throw an exception
        // due to foreign key type mismatch.
        $payout = Payout::create([
            'settlement_id' => $settlement->id,
            'recipient_type' => 'App\Models\User',
            'recipient_id' => $settlement->recipient_id,
            'amount' => 15000,
            'currency' => 'UGX',
            'method' => 'mobile_money',
            'status' => 'pending',
        ]);

        $this->assertNotNull($payout->id);
        $this->assertEquals($settlement->id, $payout->settlement_id);
        
        // Assert relation works
        $this->assertEquals($settlement->id, $payout->settlement->id);
    }
}
