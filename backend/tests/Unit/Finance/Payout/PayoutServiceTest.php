<?php

namespace Tests\Unit\Finance\Payout;

use App\Domain\Finance\Payout\PayoutService;
use App\Domain\Finance\Payout\Payout;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\Salon;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayoutService();
    }

    /** @test */
    public function it_initiates_and_executes_manual_payout()
    {
        $salon = Salon::factory()->create();
        $specialist = Specialist::factory()->create();

        $settlement = Settlement::create([
            'payable_type' => get_class($salon),
            'payable_id' => $salon->id,
            'recipient_type' => get_class($specialist),
            'recipient_id' => $specialist->id,
            'amount' => 50000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
        ]);

        $payout = $this->service->initiatePayout(
            settlement: $settlement,
            amount: 50000,
            method: 'cash',
            executionMode: 'manual',
            payload: ['provider_reference' => 'cash-receipt-1']
        );

        $this->assertEquals('completed', $payout->status);
        $this->assertEquals('cash', $payout->method);
        $this->assertEquals('manual', $payout->execution_mode);
        $this->assertNotNull($payout->completed_at);
        $this->assertEquals('cash-receipt-1', $payout->provider_reference);

        $settlement->refresh();
        $this->assertEquals(Settlement::STATUS_PAID, $settlement->status);
    }

    /** @test */
    public function it_initiates_automated_payout()
    {
        $salon = Salon::factory()->create();
        $specialist = Specialist::factory()->create();

        $settlement = Settlement::create([
            'payable_type' => get_class($salon),
            'payable_id' => $salon->id,
            'recipient_type' => get_class($specialist),
            'recipient_id' => $specialist->id,
            'amount' => 30000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
        ]);

        $payout = $this->service->initiatePayout(
            settlement: $settlement,
            amount: 30000,
            method: 'mtn',
            executionMode: 'automated',
            payload: ['provider' => 'yo_uganda']
        );

        $this->assertEquals('processing', $payout->status);
        $this->assertEquals('automated', $payout->execution_mode);
        $this->assertEquals('yo_uganda', $payout->provider);

        $settlement->refresh();
        $this->assertEquals(Settlement::STATUS_PENDING, $settlement->status); // unchanged until webhook completes payout
    }
}
