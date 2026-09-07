<?php

namespace Tests\Unit\Finance\Compensation;

use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Compensation\EarningCalculator;
use App\Models\Booking;
use App\Models\Service;
use Tests\TestCase;

class EarningCalculatorTest extends TestCase
{
    private EarningCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new EarningCalculator();
    }

    public function test_commission_calculation()
    {
        $policy = new CompensationPolicy([
            'type' => 'commission',
            'rules' => [
                ['service_category' => '*', 'rate' => 0.40],
            ],
        ]);

        $booking = new Booking(['amount_paid' => 10000]);

        $result = $this->calculator->calculate($policy, $booking);

        $this->assertNotNull($result);
        $this->assertEquals(4000.0, $result['amount']);
        $this->assertEquals('commission', $result['metadata']['type']);
        $this->assertEquals(0.40, $result['metadata']['rate']);
    }

    public function test_per_booking_calculation()
    {
        $policy = new CompensationPolicy([
            'type' => 'per_booking',
            'rules' => [
                ['amount' => 5000],
            ],
        ]);

        $booking = new Booking(['amount_paid' => 10000]); // Amount doesn't matter

        $result = $this->calculator->calculate($policy, $booking);

        $this->assertNotNull($result);
        $this->assertEquals(5000.0, $result['amount']);
        $this->assertEquals('per_booking', $result['metadata']['type']);
    }

    public function test_salary_and_fixed_return_null()
    {
        $policy = new CompensationPolicy([
            'type' => 'salary',
            'rules' => [['amount' => 500000]],
        ]);

        $booking = new Booking(['amount_paid' => 10000]);

        $result = $this->calculator->calculate($policy, $booking);

        $this->assertNull($result);
    }

    public function test_hybrid_calculates_commission_portion()
    {
        $policy = new CompensationPolicy([
            'type' => 'hybrid',
            'rules' => [
                ['type' => 'base', 'amount' => 300000],
                ['type' => 'commission', 'rate' => 0.20, 'service_category' => '*'],
            ],
        ]);

        $booking = new Booking(['amount_paid' => 10000]);

        $result = $this->calculator->calculate($policy, $booking);

        $this->assertNotNull($result);
        $this->assertEquals(2000.0, $result['amount']);
        $this->assertEquals('hybrid_commission', $result['metadata']['type']);
    }
}
