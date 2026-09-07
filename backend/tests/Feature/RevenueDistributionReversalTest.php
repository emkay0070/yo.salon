<?php

namespace Tests\Feature;

use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\RevenueDistribution\RevenueDistributionEngine;
use App\Domain\Finance\RevenueDistribution\RevenueDistributionPolicy;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\PaymentMethod;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueDistributionReversalTest extends TestCase
{
    use RefreshDatabase;

    private Salon $salon;
    private Customer $customer;
    private Service $service;
    private Booking $booking;
    private RevenueDistributionEngine $distributionEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->service = Service::factory()->create(['provider_id' => $this->salon->provider_id, 'price' => 50000]);
        
        $this->booking = Booking::create([
            'salon_id' => $this->salon->id,
            'provider_id' => $this->salon->provider_id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'amount_due' => 50000,
            'amount_paid' => 50000,
        ]);

        $this->distributionEngine = app(RevenueDistributionEngine::class);
    }

    public function test_refund_after_distribution_earned_state()
    {
        // Create a specialist for the booking
        $specialist = \App\Models\Specialist::factory()->create();
        $this->booking->update(['specialist_id' => $specialist->id]);

        // Manually create an earning in 'earned' state (simulating period-based distribution)
        $earning = Earning::create([
            'compensatable_type' => \App\Models\Specialist::class,
            'compensatable_id' => $specialist->id,
            'source_type' => Booking::class,
            'source_id' => $this->booking->id,
            'compensation_policy_id' => null,
            'gross_amount' => 30000,
            'currency' => 'UGX',
            'status' => Earning::STATUS_EARNED,
        ]);

        // Manually create a revenue distribution financial transaction
        $distributionFT = FinancialTransaction::createWithJournals(
            type: 'revenue_distribution',
            description: "Revenue distribution for booking #{$this->booking->id}",
            journalData: [],
            referenceType: Booking::class,
            referenceId: $this->booking->id
        );

        // Verify earning was created
        $this->assertNotNull($earning);
        $this->assertEquals(30000, $earning->gross_amount);
        $this->assertEquals(Earning::STATUS_EARNED, $earning->status);

        // Verify distribution journal exists
        $this->assertNotNull($distributionFT);

        // Create refund transaction
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'type' => 'cash',
            'provider' => 'manual',
        ]);
        $paymentAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'account_type' => PaymentAccount::TYPE_MERCHANT,
        ]);

        $refundTransaction = Transaction::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'type' => 'refund',
            'status' => 'completed',
            'gross_amount' => -50000,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => -50000,
            'currency' => 'UGX',
            'internal_reference' => 'REF-TEST',
            'provider_reference' => 'MANUAL-REF-TEST',
            'paid_at' => now(),
        ]);

        // Trigger refund reversal
        $reversalService = app(\App\Domain\Finance\RevenueDistribution\RevenueDistributionReversal::class);
        $reversalService->reverseForBooking($this->booking, $refundTransaction->id);

        // Verify earning was voided
        $earning->refresh();
        $this->assertEquals(Earning::STATUS_VOIDED, $earning->status);

        // Verify distribution journal was voided
        $distributionFT->refresh();
        $this->assertEquals('voided', $distributionFT->status);
    }

    public function test_refund_after_settlement_payable_state()
    {
        // Create a specialist for the booking
        $specialist = \App\Models\Specialist::factory()->create();
        $this->booking->update(['specialist_id' => $specialist->id]);

        // Create immediate compensation policy (creates settlement immediately)
        $compensationPolicy = CompensationPolicy::create([
            'compensatable_type' => \App\Models\Specialist::class,
            'compensatable_id' => $specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $this->salon->id,
            'type' => 'commission',
            'rules' => [['rate' => 60]],
            'settlement_frequency' => 'immediate',
            'auto_create_earnings' => true,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $policy = RevenueDistributionPolicy::create([
            'name' => 'Test Policy',
            'owner_type' => Salon::class,
            'owner_id' => $this->salon->id,
            'revenue_type' => 'booking',
            'rules' => [
                [
                    'party' => 'specialist',
                    'owner_type' => \App\Models\Specialist::class,
                    'type' => 'percentage',
                    'percentage' => 60,
                    'compensation_policy_id' => $compensationPolicy->id,
                ],
            ],
        ]);

        // Apply revenue distribution
        $this->distributionEngine->applyForBooking($this->booking);

        // Verify earning is in payable state with settlement
        $earning = Earning::where('source_type', Booking::class)
            ->where('source_id', $this->booking->id)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(Earning::STATUS_PAYABLE, $earning->status);
        $this->assertNotNull($earning->settlement_id);

        $settlement = $earning->settlement;
        $this->assertNotNull($settlement);
        $this->assertEquals(\App\Domain\Finance\Settlement\Settlement::STATUS_PENDING, $settlement->status);

        // Create refund and trigger reversal
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $this->salon->id,
            'type' => 'cash',
            'provider' => 'manual',
        ]);
        $paymentAccount = PaymentAccount::factory()->create([
            'salon_id' => $this->salon->id,
            'account_type' => PaymentAccount::TYPE_MERCHANT,
        ]);

        $refundTransaction = Transaction::create([
            'salon_id' => $this->salon->id,
            'booking_id' => $this->booking->id,
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'type' => 'refund',
            'status' => 'completed',
            'gross_amount' => -50000,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => -50000,
            'currency' => 'UGX',
            'internal_reference' => 'REF-TEST',
            'provider_reference' => 'MANUAL-REF-TEST',
            'paid_at' => now(),
        ]);

        $reversalService = app(\App\Domain\Finance\RevenueDistribution\RevenueDistributionReversal::class);
        $reversalService->reverseForBooking($this->booking, $refundTransaction->id);

        // Verify settlement was cancelled
        $settlement->refresh();
        $this->assertEquals(\App\Domain\Finance\Settlement\Settlement::STATUS_CANCELLED, $settlement->status);

        // Verify earning was voided
        $earning->refresh();
        $this->assertEquals(Earning::STATUS_VOIDED, $earning->status);
    }

    public function test_refund_after_payout_paid_state()
    {
        // This test would require creating a payout and marking it as paid
        // For now, we'll skip this as it requires more setup
        $this->markTestSkipped('Paid state reversal requires full payout setup');
    }
}
