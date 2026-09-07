<?php

namespace Tests\Unit\Finance\Compensation;

use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\Compensation\PeriodClosingService;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\Salon;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodClosingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PeriodClosingService $service;
    private Salon $salon;
    private Specialist $specialist;
    private CompensationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        
        $this->service = new PeriodClosingService();
        $this->salon = Salon::factory()->create();
        $this->specialist = Specialist::factory()->create();

        $this->policy = CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'type' => 'commission',
            'rules' => [['service_category' => '*', 'rate' => 0.40]],
            'settlement_frequency' => 'monthly',
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);
    }

    public function test_close_due_periods_only_closes_past_periods()
    {
        // Past period - should be closed
        $pastPeriod = CompensationPeriod::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'compensation_policy_id' => $this->policy->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'status' => CompensationPeriod::STATUS_OPEN,
            'gross_amount' => 10000,
            'payable_amount' => 10000,
        ]);

        $earning = Earning::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'source_type' => 'App\Models\Booking',
            'source_id' => 'dummy',
            'compensation_policy_id' => $this->policy->id,
            'compensation_period_id' => $pastPeriod->id,
            'gross_amount' => 10000,
            'status' => Earning::STATUS_INCLUDED,
        ]);

        // Current period - should NOT be closed
        $currentPeriod = CompensationPeriod::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'compensation_policy_id' => $this->policy->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => CompensationPeriod::STATUS_OPEN,
            'gross_amount' => 5000,
            'payable_amount' => 5000,
        ]);

        $this->service->closeDuePeriods();

        $pastPeriod->refresh();
        $this->assertEquals(CompensationPeriod::STATUS_CLOSED, $pastPeriod->status);
        $this->assertNotNull($pastPeriod->settlement_id);

        $currentPeriod->refresh();
        $this->assertEquals(CompensationPeriod::STATUS_OPEN, $currentPeriod->status);
        $this->assertNull($currentPeriod->settlement_id);

        $earning->refresh();
        $this->assertEquals(Earning::STATUS_PAYABLE, $earning->status);
        $this->assertEquals($pastPeriod->settlement_id, $earning->settlement_id);

        $settlement = Settlement::find($pastPeriod->settlement_id);
        $this->assertNotNull($settlement);
        $this->assertEquals(10000, $settlement->amount);
    }

    public function test_salary_base_earning_generation()
    {
        $salaryPolicy = CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'type' => 'salary',
            'rules' => [['amount' => 650000]],
            'settlement_frequency' => 'monthly',
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        $period = CompensationPeriod::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'compensation_policy_id' => $salaryPolicy->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'status' => CompensationPeriod::STATUS_OPEN,
            'currency' => 'UGX',
            'gross_amount' => 0,
        ]);

        $this->service->closePeriod($period);

        $period->refresh();
        $this->assertEquals(CompensationPeriod::STATUS_CLOSED, $period->status);
        $this->assertEquals(650000.0, $period->gross_amount);
        $this->assertEquals(650000.0, $period->payable_amount);

        $earning = Earning::where('compensation_period_id', $period->id)->first();
        $this->assertNotNull($earning);
        $this->assertEquals(650000.0, $earning->gross_amount);
        $this->assertEquals(CompensationPeriod::class, $earning->source_type);
        $this->assertEquals($period->id, $earning->source_id);
        $this->assertEquals(Earning::STATUS_PAYABLE, $earning->status);
    }
}
