<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\Events\RevenueDistributionRequested;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\RevenueDistribution\RevenueDistributionEngine;
use App\Domain\Finance\RevenueDistribution\RevenueDistributionPolicy;
use App\Domain\Finance\Settlement\Settlement;
use App\Domain\Finance\Events\EarningRecognized;
use App\Models\Booking;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RevenueDistributionEngineTest extends TestCase
{
    use RefreshDatabase;

    private RevenueDistributionEngine $engine;
    private Provider $provider;
    private Booking $booking;
    private RevenueDistributionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        
        $this->engine = new RevenueDistributionEngine();
        
        // Setup a mock provider and booking
        $this->provider = Provider::factory()->create();
        $location = \App\Models\Location::factory()->create(['provider_id' => $this->provider->id]);
        // Satisfy the provider_id and location_id foreign keys for the booking
        $this->booking = \App\Models\Booking::factory()->create([
            'location_id' => $location->id,
            'provider_id' => $this->provider->id,
            'specialist_id' => \App\Models\Specialist::factory()->create()->id,
            'status' => 'completed',
            'amount_due' => 30000,
            'amount_paid' => 30000,
        ]);

        // Create payment transaction for the booking (required for revenue distribution)
        $paymentMethod = \App\Models\PaymentMethod::factory()->create([
            'provider_id' => $this->provider->id,
            'type' => 'cash',
            'provider' => 'manual',
        ]);
        $paymentAccount = \App\Models\PaymentAccount::factory()->create([
            'provider_id' => $this->provider->id,
            'account_type' => \App\Models\PaymentAccount::TYPE_MERCHANT,
        ]);

        \App\Models\Transaction::create([
            'provider_id' => $this->provider->id,
            'booking_id' => $this->booking->id,
            'customer_id' => \App\Models\Customer::factory()->create()->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'type' => 'payment',
            'status' => 'paid',
            'gross_amount' => 30000,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 30000,
            'currency' => 'UGX',
            'internal_reference' => 'PAY-TEST',
            'provider_reference' => 'MANUAL-PAY-TEST',
            'paid_at' => now(),
        ]);

        // Setup a standard distribution policy:
        // Specialist gets 60% (18,000)
        // Provider keeps the rest
        $this->policy = RevenueDistributionPolicy::create([
            'context_type' => Provider::class,
            'context_id' => $this->provider->id,
            'revenue_type' => 'booking',
            'name' => 'Standard 60/40 Split',
            'is_active' => true,
            'rules' => [
                [
                    'party' => 'specialist',
                    'type' => 'percentage',
                    'percentage' => 60,
                    'owner_type' => \App\Models\Specialist::class,
                    'compensation_policy_id' => (string) \Illuminate\Support\Str::uuid(),
                ],
                [
                    'party' => 'payable', // Provider's share, skipped by engine as it's already in revenue
                    'type' => 'percentage',
                    'percentage' => 40,
                ]
            ]
        ]);
    }

    public function test_distribution_journal_balances_and_creates_correct_entries()
    {
        Event::fake([EarningRecognized::class]);

        $this->engine->applyForBooking($this->booking);

        // 1. Verify Financial Transaction
        $ft = FinancialTransaction::where('type', 'revenue_distribution')
            ->where('reference_type', Booking::class)
            ->where('reference_id', $this->booking->id)
            ->first();
            
        $this->assertNotNull($ft, 'Revenue distribution financial transaction was not created.');
        
        // 2. Verify Journal Entry balances (enforced by DB/Service, but let's check accounts)
        $allocationAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $this->provider->id)
            ->where('account_type', 'revenue_allocation')
            ->first();
            
        $specialistPayableAccount = LedgerAccount::where('owner_type', \App\Models\Specialist::class)
            ->where('owner_id', $this->booking->specialist_id)
            ->where('account_type', 'payable')
            ->first();
            
        $this->assertNotNull($allocationAccount);
        $this->assertNotNull($specialistPayableAccount);
        
        // DR Revenue Allocation (reduces provider revenue, debit-normal) => Raw -18,000
        $this->assertEquals(-18000, $allocationAccount->balance);
        $this->assertEquals(18000, $allocationAccount->economic_balance); // Positive economic reduction
        
        // CR Specialist Payable (liability, credit-normal) => Raw +18,000
        $this->assertEquals(18000, $specialistPayableAccount->balance);
        $this->assertEquals(18000, $specialistPayableAccount->economic_balance);
        
        // 3. Verify EarningRecognized event was dispatched
        Event::assertDispatched(EarningRecognized::class, function ($event) {
            return $event->compensatableType === \App\Models\Specialist::class
                && $event->compensatableId === $this->booking->specialist_id
                && $event->sourceType === Booking::class
                && $event->sourceId === $this->booking->id
                && $event->amount === 18000.0;
        });
    }

    public function test_distribution_is_idempotent()
    {
        Event::fake([EarningRecognized::class]);

        // First run
        $this->engine->applyForBooking($this->booking);
        $ftCount1 = FinancialTransaction::where('type', 'revenue_distribution')->count();
        Event::assertDispatched(EarningRecognized::class, 1);
        
        // Second run - should be silent no-op
        $this->engine->applyForBooking($this->booking);
        $ftCount2 = FinancialTransaction::where('type', 'revenue_distribution')->count();
        Event::assertDispatched(EarningRecognized::class, 1); // Still 1
        
        $this->assertEquals(1, $ftCount1);
        $this->assertEquals(1, $ftCount2, 'Idempotency failed: multiple financial transactions created.');
    }
}
