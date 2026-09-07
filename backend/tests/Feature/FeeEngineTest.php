<?php

namespace Tests\Feature;

use App\Services\FeeEngine;
use Tests\TestCase;

class FeeEngineTest extends TestCase
{
    protected FeeEngine $feeEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feeEngine = app(FeeEngine::class);
    }

    public function test_zero_platform_fee_for_core_booking(): void
    {
        $fees = $this->feeEngine->calculateFees(3000, null);

        $this->assertEquals(3000, $fees['gross_amount']);
        $this->assertEquals(0, $fees['platform_fee']);
        $this->assertEquals(0, $fees['gateway_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEquals(3000, $fees['net_amount']);
    }

    public function test_zero_gateway_fee_by_default(): void
    {
        $fees = $this->feeEngine->calculateFees(3000, null);

        $this->assertEquals(0, $fees['gateway_fee']);
        $this->assertEquals(3000, $fees['net_amount']);
    }

    public function test_null_payment_method_returns_zero_fees(): void
    {
        $fees = $this->feeEngine->calculateFees(3000, null);

        $this->assertEquals(3000, $fees['gross_amount']);
        $this->assertEquals(0, $fees['gateway_fee']);
        $this->assertEquals(0, $fees['platform_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEquals(3000, $fees['net_amount']);
    }

    public function test_zero_amount_transaction(): void
    {
        $fees = $this->feeEngine->calculateFees(0, null);

        $this->assertEquals(0, $fees['gross_amount']);
        $this->assertEquals(0, $fees['gateway_fee']);
        $this->assertEquals(0, $fees['platform_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEquals(0, $fees['net_amount']);
    }

    public function test_large_amount_transaction(): void
    {
        $fees = $this->feeEngine->calculateFees(100000, null);

        $this->assertEquals(100000, $fees['gross_amount']);
        $this->assertEquals(0, $fees['gateway_fee']);
        $this->assertEquals(0, $fees['platform_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEquals(100000, $fees['net_amount']);
    }

    public function test_fee_structure_return_format(): void
    {
        $fees = $this->feeEngine->calculateFees(5000, null);

        $this->assertIsArray($fees);
        $this->assertArrayHasKey('gross_amount', $fees);
        $this->assertArrayHasKey('gateway_fee', $fees);
        $this->assertArrayHasKey('platform_fee', $fees);
        $this->assertArrayHasKey('tax_amount', $fees);
        $this->assertArrayHasKey('net_amount', $fees);
    }

    public function test_calculate_fees_from_provider(): void
    {
        $providerResponse = [
            'fees' => 83.7,
            'amount' => 3000
        ];

        $fees = $this->feeEngine->calculateFeesFromProvider(3000, null, $providerResponse);

        $this->assertEquals(3000, $fees['gross_amount']);
        $this->assertEquals(83.7, $fees['gateway_fee']);
        $this->assertEquals(0, $fees['platform_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEquals(2916.3, $fees['net_amount']);
        
        // Net invariant check
        $this->assertEquals($fees['gross_amount'], $fees['gateway_fee'] + $fees['platform_fee'] + $fees['tax_amount'] + $fees['net_amount']);
    }

    public function test_calculate_fees_with_config_mobile_money(): void
    {
        $paymentMethod = \App\Models\PaymentMethod::factory()->make(['type' => 'mobile_money']);
        $config = ['mobile_money_rate' => 0.02, 'platform_fee' => 100];

        $fees = $this->feeEngine->calculateFeesWithConfig(5000, $paymentMethod, $config);

        $this->assertEquals(5000, $fees['gross_amount']);
        $this->assertEquals(100, $fees['gateway_fee']); // 5000 * 0.02
        $this->assertEquals(100, $fees['platform_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEquals(4800, $fees['net_amount']);
        
        $this->assertEquals($fees['gross_amount'], $fees['gateway_fee'] + $fees['platform_fee'] + $fees['tax_amount'] + $fees['net_amount']);
    }
    
    public function test_calculate_fees_with_config_card(): void
    {
        $paymentMethod = \App\Models\PaymentMethod::factory()->make(['type' => 'card']);
        $config = ['card_rate' => 0.035, 'platform_fee' => 0];

        $fees = $this->feeEngine->calculateFeesWithConfig(10000, $paymentMethod, $config);

        $this->assertEquals(10000, $fees['gross_amount']);
        $this->assertEqualsWithDelta(350, $fees['gateway_fee'], 0.01); // 10000 * 0.035
        $this->assertEquals(0, $fees['platform_fee']);
        $this->assertEquals(0, $fees['tax_amount']);
        $this->assertEqualsWithDelta(9650, $fees['net_amount'], 0.01);
        
        $this->assertEqualsWithDelta($fees['gross_amount'], $fees['gateway_fee'] + $fees['platform_fee'] + $fees['tax_amount'] + $fees['net_amount'], 0.01);
    }
}
