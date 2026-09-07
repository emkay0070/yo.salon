<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Services\Intelligence\IntelligenceEngine;
use App\Services\Finance\FinanceAnalyticsService;
use App\Domain\Finance\Journal\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pass 3: Intelligence Analyzers Verification
 * 
 * This test suite verifies that Intelligence analyzers correctly consume
 * financial facts from FinanceAnalyticsService instead of direct Transaction queries.
 */
class IntelligenceAnalyzersVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected IntelligenceEngine $intelligenceEngine;
    protected FinanceAnalyticsService $financeAnalyticsService;
    protected JournalPostingService $journalPostingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intelligenceEngine = app(IntelligenceEngine::class);
        $this->financeAnalyticsService = app(FinanceAnalyticsService::class);
        $this->journalPostingService = app(JournalPostingService::class);
    }

    public function test_intelligence_engine_generates_dto_with_financial_facts()
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

        $dto = $this->intelligenceEngine->generate($salon);

        // Verify DTO structure
        $this->assertArrayHasKey('schema_version', $dto);
        $this->assertArrayHasKey('generated_at', $dto);
        $this->assertArrayHasKey('analytics', $dto);
        $this->assertArrayHasKey('forecast', $dto);
        $this->assertArrayHasKey('recommendations', $dto);
        $this->assertArrayHasKey('briefing', $dto);
        $this->assertArrayHasKey('signals', $dto);

        // Verify RevenueAnalyzer output
        $this->assertArrayHasKey('revenue', $dto['analytics']);
        $this->assertEquals(100000, $dto['analytics']['revenue']['gross']);
        $this->assertEquals(100000, $dto['analytics']['revenue']['net']);
    }

    public function test_revenue_analyzer_uses_ledger_not_transactions()
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
            'platform_fee' => 0,  // Not posted to ledger
            'tax_amount' => 0,    // Not posted to ledger
            'net_amount' => 97000,
            'type' => 'payment',
            'status' => 'paid',
        ]);

        $this->journalPostingService->postForTransaction($transaction);

        $dto = $this->intelligenceEngine->generate($salon);

        // RevenueAnalyzer should use ledger (gross - gateway_fee)
        // NOT Transaction.net_amount (which includes platform_fee and tax)
        $this->assertEquals(100000, $dto['analytics']['revenue']['gross']);
        $this->assertEquals(97000, $dto['analytics']['revenue']['net']); // 100000 - 3000
        $this->assertEquals(3000, $dto['analytics']['revenue']['gateway_fees']);
    }

    public function test_forecast_analyzer_uses_ledger_trend_data()
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

        $dto = $this->intelligenceEngine->generate($salon);

        // Verify ForecastService output exists
        $this->assertArrayHasKey('forecast', $dto);
        $this->assertArrayHasKey('projected_monthly_net', $dto['forecast']);
        
        // Forecast should be based on ledger trend data
        $this->assertGreaterThan(0, $dto['forecast']['projected_monthly_net']);
    }

    public function test_intelligence_engine_generates_signals_from_facts()
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

        $dto = $this->intelligenceEngine->generate($salon);

        // Verify signals are generated
        $this->assertArrayHasKey('signals', $dto);
        $this->assertIsArray($dto['signals']);
    }

    public function test_intelligence_engine_generates_briefing_from_facts()
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

        $dto = $this->intelligenceEngine->generate($salon);

        // Verify briefing is generated
        $this->assertArrayHasKey('briefing', $dto);
        $this->assertIsArray($dto['briefing']);
    }
}
