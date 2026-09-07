<?php

namespace Tests\Unit\Finance\Compensation;

use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\Events\EarningRecognized;
use App\Domain\Finance\Settlement\Settlement;
use App\Listeners\Finance\RecordEarning;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordEarningListenerTest extends TestCase
{
    use RefreshDatabase;

    private RecordEarning $listener;
    private Salon $salon;
    private Specialist $specialist;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        
        $this->listener = new RecordEarning();

        $this->salon = Salon::factory()->create();
        $this->specialist = Specialist::factory()->create();
        
        $this->booking = Booking::factory()->create([
            'salon_id' => $this->salon->id,
            'specialist_id' => $this->specialist->id,
            'status' => 'completed',
        ]);
    }

    public function test_immediate_policy_creates_settlement()
    {
        $policy = CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'type' => 'commission',
            'rules' => [['service_category' => '*', 'rate' => 0.40]],
            'settlement_frequency' => 'immediate',
            'effective_from' => now(),
            'is_active' => true,
        ]);

        $event = new EarningRecognized(
            compensatableType: Specialist::class,
            compensatableId: $this->specialist->id,
            sourceType: Booking::class,
            sourceId: $this->booking->id,
            policyId: $policy->id,
            amount: 12000.0
        );

        $this->listener->handle($event);

        $earning = Earning::first();
        $this->assertNotNull($earning);
        $this->assertEquals(12000.0, $earning->gross_amount);
        $this->assertEquals(Earning::STATUS_PAYABLE, $earning->status);

        $settlement = Settlement::first();
        $this->assertNotNull($settlement);
        $this->assertEquals(12000.0, $settlement->amount);
        $this->assertEquals($earning->settlement_id, $settlement->id);
    }

    public function test_period_policy_adds_to_open_period()
    {
        $policy = CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'type' => 'commission',
            'rules' => [['service_category' => '*', 'rate' => 0.40]],
            'settlement_frequency' => 'monthly',
            'effective_from' => now(),
            'is_active' => true,
        ]);

        $event = new EarningRecognized(
            compensatableType: Specialist::class,
            compensatableId: $this->specialist->id,
            sourceType: Booking::class,
            sourceId: $this->booking->id,
            policyId: $policy->id,
            amount: 12000.0
        );

        $this->listener->handle($event);

        $earning = Earning::first();
        $this->assertNotNull($earning);
        $this->assertEquals(12000.0, $earning->gross_amount);
        $this->assertEquals(Earning::STATUS_INCLUDED, $earning->status);

        $period = CompensationPeriod::first();
        $this->assertNotNull($period);
        $this->assertEquals(12000.0, $period->gross_amount);
        $this->assertEquals(CompensationPeriod::STATUS_OPEN, $period->status);
        $this->assertEquals($period->id, $earning->compensation_period_id);
        
        $this->assertEquals(0, Settlement::count()); // No settlement yet
    }
}
