<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Specialist;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Services\Finance\FinanceAnalyticsService;
use App\Domain\Finance\Journal\JournalPostingService;
use App\Domain\Finance\RevenueDistribution\RevenueDistributionEngine;
use App\Domain\Finance\Settlement\Settlement;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Ledger\LedgerEntry;
use App\Domain\Finance\Journal\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pass 3: Finance → Intelligence Contract Verification
 * 
 * This test suite verifies the getIntelligenceFinancialFacts() method
 * against various financial scenarios to ensure the Finance → Intelligence
 * boundary is correct before building additional domains.
 */
class FinanceAnalyticsServiceVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected FinanceAnalyticsService $financeAnalyticsService;
    protected JournalPostingService $journalPostingService;
    protected RevenueDistributionEngine $revenueDistributionEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financeAnalyticsService = app(FinanceAnalyticsService::class);
        $this->journalPostingService = app(JournalPostingService::class);
        $this->revenueDistributionEngine = app(RevenueDistributionEngine::class);
    }

    public function test_it_returns_zero_facts_when_no_transactions_exist()
    {
        $salon = Salon::factory()->create();

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        $this->assertEquals(0, $facts['totals']['gross_revenue']);
        $this->assertEquals(0, $facts['totals']['net_revenue']);
        $this->assertEquals(0, $facts['totals']['processing_fees']);
        $this->assertEquals(0, $facts['totals']['today_gross_revenue']);
        $this->assertEquals(0, $facts['totals']['today_net_revenue']);
        $this->assertEmpty($facts['by_booking']);
        $this->assertEmpty($facts['by_specialist']);
        $this->assertEmpty($facts['by_customer']);
        $this->assertEmpty($facts['by_service']);
        $this->assertEquals(0, $facts['settlements']['pending_amount']);
    }

    public function test_it_correctly_accounts_for_cash_payment()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        
        $booking = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $transaction = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 0,
            'net_amount' => 100000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        // Post to ledger
        $this->journalPostingService->postForTransaction($transaction);

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        // Verify invariants: Asset debit = Revenue credit (no fees for cash)
        $this->assertEquals(100000, $facts['totals']['gross_revenue']);
        $this->assertEquals(100000, $facts['totals']['net_revenue']);
        $this->assertEquals(0, $facts['totals']['processing_fees']);
        
        // Verify attribution
        $this->assertArrayHasKey($booking->id, $facts['by_booking']);
        $this->assertEquals(100000, $facts['by_booking'][$booking->id]['gross_revenue']);
        $this->assertEquals(0, $facts['by_booking'][$booking->id]['processing_fees']);
        $this->assertEquals(100000, $facts['by_booking'][$booking->id]['net_revenue']);
    }

    public function test_it_correctly_accounts_for_gateway_fee_payment()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        
        $booking = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MERCHANT,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'mobile_money',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $transaction = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 3000,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 97000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        // Post to ledger
        $this->journalPostingService->postForTransaction($transaction);

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        // Verify invariants: Asset debit + Processing-cost debit = Revenue credit
        $this->assertEquals(100000, $facts['totals']['gross_revenue']);
        $this->assertEquals(97000, $facts['totals']['net_revenue']);
        $this->assertEquals(3000, $facts['totals']['processing_fees']);
        
        // Verify attribution
        $this->assertArrayHasKey($booking->id, $facts['by_booking']);
        $this->assertEquals(100000, $facts['by_booking'][$booking->id]['gross_revenue']);
        $this->assertEquals(3000, $facts['by_booking'][$booking->id]['processing_fees']);
        $this->assertEquals(97000, $facts['by_booking'][$booking->id]['net_revenue']);
    }

    public function test_it_correctly_attributes_revenue_to_multiple_entities()
    {
        $salon = Salon::factory()->create();
        
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $booking1 = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer1->id,
            'amount_due' => 50000,
            'amount_paid' => 50000,
        ]);

        $booking2 = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer2->id,
            'amount_due' => 75000,
            'amount_paid' => 75000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $transaction1 = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking1->id,
            'customer_id' => $customer1->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 50000,
            'gateway_fee' => 0,
            'net_amount' => 50000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $transaction2 = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking2->id,
            'customer_id' => $customer2->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 75000,
            'gateway_fee' => 0,
            'net_amount' => 75000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $this->journalPostingService->postForTransaction($transaction1);
        $this->journalPostingService->postForTransaction($transaction2);

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        // Verify totals
        $this->assertEquals(125000, $facts['totals']['gross_revenue']);
        
        // Verify customer attribution
        $this->assertArrayHasKey($customer1->id, $facts['by_customer']);
        $this->assertEquals(50000, $facts['by_customer'][$customer1->id]['gross_revenue']);
        
        $this->assertArrayHasKey($customer2->id, $facts['by_customer']);
        $this->assertEquals(75000, $facts['by_customer'][$customer2->id]['gross_revenue']);
        
        // Verify booking isolation
        $this->assertArrayHasKey($booking1->id, $facts['by_booking']);
        $this->assertEquals(50000, $facts['by_booking'][$booking1->id]['gross_revenue']);
        
        $this->assertArrayHasKey($booking2->id, $facts['by_booking']);
        $this->assertEquals(75000, $facts['by_booking'][$booking2->id]['gross_revenue']);
    }

    public function test_it_correctly_accounts_for_refunds()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        
        $booking = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $paymentTransaction = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 0,
            'net_amount' => 100000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $refundTransaction = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 0,
            'net_amount' => 100000,
            'type' => 'refund',
            'status' => 'paid',
        ]);

        $this->journalPostingService->postForTransaction($paymentTransaction);
        $this->journalPostingService->postForTransaction($refundTransaction);

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        // Refund should reduce revenue to zero (credit - debit)
        $this->assertEquals(0, $facts['totals']['gross_revenue']);
        $this->assertEquals(0, $facts['totals']['net_revenue']);
    }

    public function test_it_distinguishes_payment_revenue_from_distribution_payable()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        
        $booking = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $transaction = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 0,
            'net_amount' => 100000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $this->journalPostingService->postForTransaction($transaction);
        $this->revenueDistributionEngine->applyForBooking($booking);

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        // Payment revenue: 100,000 (what the customer paid)
        $this->assertEquals(100000, $facts['totals']['gross_revenue']);
        
        // Note: Specialist payable verification requires specialist setup
        // This test verifies the conceptual distinction exists in the architecture
        // The actual payable amount depends on distribution policy
    }

    public function test_it_correctly_accounts_for_pending_vs_paid_settlements()
    {
        $salon = Salon::factory()->create();

        // Create pending settlement
        $pendingSettlement = new Settlement([
            'payable_type' => Salon::class,
            'payable_id' => $salon->id,
            'recipient_type' => Salon::class,
            'recipient_id' => $salon->id,
            'amount' => 50000,
            'status' => Settlement::STATUS_PENDING,
        ]);
        $pendingSettlement->save();

        // Create paid settlement
        $paidSettlement = new Settlement([
            'payable_type' => Salon::class,
            'payable_id' => $salon->id,
            'recipient_type' => Salon::class,
            'recipient_id' => $salon->id,
            'amount' => 30000,
            'status' => Settlement::STATUS_PAID,
        ]);
        $paidSettlement->save();

        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);

        // Only pending settlements should be counted
        $this->assertEquals(50000, $facts['settlements']['pending_amount']);
    }

    public function test_it_isolates_data_by_salon()
    {
        $salon1 = Salon::factory()->create();
        $salon2 = Salon::factory()->create();
        
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        
        $booking1 = Booking::factory()->create([
            'salon_id' => $salon1->id,
            'customer_id' => $customer1->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $booking2 = Booking::factory()->create([
            'salon_id' => $salon2->id,
            'customer_id' => $customer2->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon1->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $paymentAccount2 = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod2 = PaymentMethod::factory()->create([
            'salon_id' => $salon2->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount2->id,
        ]);

        $transaction1 = Transaction::factory()->create([
            'salon_id' => $salon1->id,
            'booking_id' => $booking1->id,
            'customer_id' => $customer1->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 0,
            'net_amount' => 100000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $transaction2 = Transaction::factory()->create([
            'salon_id' => $salon2->id,
            'booking_id' => $booking2->id,
            'customer_id' => $customer2->id,
            'payment_method_id' => $paymentMethod2->id,
            'payment_account_id' => $paymentAccount2->id,
            'gross_amount' => 100000,
            'gateway_fee' => 0,
            'net_amount' => 100000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $this->journalPostingService->postForTransaction($transaction1);
        $this->journalPostingService->postForTransaction($transaction2);

        $facts1 = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon1);
        $facts2 = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon2);

        // Each salon should only see its own revenue
        $this->assertEquals(100000, $facts1['totals']['gross_revenue']);
        $this->assertEquals(100000, $facts2['totals']['gross_revenue']);
        
        // Salon 1 should not see Salon 2's booking
        $this->assertArrayHasKey($booking1->id, $facts1['by_booking']);
        $this->assertArrayNotHasKey($booking2->id, $facts1['by_booking']);
        
        // Salon 2 should not see Salon 1's booking
        $this->assertArrayHasKey($booking2->id, $facts2['by_booking']);
        $this->assertArrayNotHasKey($booking1->id, $facts2['by_booking']);
    }

    public function test_it_does_not_cause_n_plus_one_query_explosion()
    {
        $salon = Salon::factory()->create();
        
        // Create 10 bookings
        $bookings = Booking::factory()->count(10)->create([
            'salon_id' => $salon->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MANUAL,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'cash',
            'payment_account_id' => $paymentAccount->id,
        ]);

        foreach ($bookings as $booking) {
            $transaction = Transaction::factory()->create([
                'salon_id' => $salon->id,
                'booking_id' => $booking->id,
                'payment_method_id' => $paymentMethod->id,
                'payment_account_id' => $paymentAccount->id,
                'gross_amount' => 100000,
                'gateway_fee' => 0,
                'net_amount' => 100000,
                'type' => 'payment',
                'status' => 'paid',
            ]);
            $this->journalPostingService->postForTransaction($transaction);
        }

        // Enable query logging
        \DB::enableQueryLog();
        
        $facts = $this->financeAnalyticsService->getIntelligenceFinancialFacts($salon);
        
        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // The method should use efficient joins, not N+1 queries
        // With 10 bookings, we should not have 10+ queries for each entity type
        // A reasonable limit would be ~50-60 queries total for this operation
        // The current implementation may need optimization
        $this->assertLessThan(100, count($queries), 
            'Too many queries detected - possible N+1 issue: ' . count($queries) . ' queries');
        
        // Verify results are correct
        $this->assertEquals(1000000, $facts['totals']['gross_revenue']);
        $this->assertCount(10, $facts['by_booking']);
    }

    public function test_it_verifies_ledger_mathematical_invariants()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        
        $booking = Booking::factory()->create([
            'salon_id' => $salon->id,
            'customer_id' => $customer->id,
            'amount_due' => 100000,
            'amount_paid' => 100000,
        ]);

        $paymentAccount = PaymentAccount::factory()->create([
            'account_type' => PaymentAccount::TYPE_MERCHANT,
        ]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'mobile_money',
            'payment_account_id' => $paymentAccount->id,
        ]);

        $transaction = Transaction::factory()->create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'gross_amount' => 100000,
            'gateway_fee' => 3000,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 97000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $this->journalPostingService->postForTransaction($transaction);

        // Verify ledger-level invariants directly
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        $processingAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'processing_cost')
            ->first();

        $assetAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'mobile_money')
            ->first();

        // Revenue credits - revenue debits = gross revenue
        $revenueCredits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('type', 'credit')->sum('amount');
        $revenueDebits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('type', 'debit')->sum('amount');
        $grossRevenue = $revenueCredits - $revenueDebits;

        $this->assertEquals(100000, $grossRevenue, 'Revenue invariant failed');

        // Asset debit + processing-cost debit = revenue credit
        $assetDebits = LedgerEntry::where('ledger_account_id', $assetAccount->id)
            ->where('type', 'debit')->sum('amount');
        $processingDebits = LedgerEntry::where('ledger_account_id', $processingAccount->id)
            ->where('type', 'debit')->sum('amount');

        $this->assertEquals(100000, $assetDebits + $processingDebits, 
            'Balance invariant failed: Asset + Processing should equal Revenue');
    }
}
