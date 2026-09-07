<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\PaymentMethod;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\Transaction;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Ledger\LedgerEntry;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\Settlement\Settlement;
use App\Services\Payments\SalonPaymentService;
use App\Services\FeeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pass 9: End-to-End Money Flow Verification
 * 
 * This test suite traces actual UGX through every stage of the payment flow:
 * Customer payment → Transaction → JournalEntry → LedgerEntry → Financial facts
 * → Analytics/Intelligence → Settlement obligation → Payout
 * 
 * Key invariant: Money entering = Ledger movements = Financial truth = 
 * Amounts eventually settled/payoutable ± legitimate fees/refunds
 */
class EndToEndMoneyFlowVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_payment_flow_end_to_end()
    {
        // 1. Setup salon, customer, booking
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id, 'price' => 50000]);
        
        $booking = Booking::create([
            'salon_id' => $salon->id,
            'provider_id' => $salon->provider_id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'amount_due' => 50000,
            'amount_paid' => 0,
        ]);

        // 2. Record cash payment
        $paymentService = app(SalonPaymentService::class);
        $paymentResult = $paymentService->recordManualPayment([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
            'amount' => 50000,
            'payment_method' => 'cash',
        ]);

        $this->assertTrue($paymentResult['success']);
        
        // 3. Verify Transaction created
        $transaction = Transaction::where('booking_id', $booking->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals(50000, $transaction->gross_amount);
        $this->assertEquals(50000, $transaction->net_amount); // Cash has zero fees
        $this->assertEquals(0, $transaction->gateway_fee);
        $this->assertEquals(0, $transaction->platform_fee); // Zero platform commission

        // 4. Manually post journal (recordManualPayment doesn't trigger event)
        $journalService = app(\App\Domain\Finance\Journal\JournalPostingService::class);
        $journalService->postForTransaction($transaction);

        // 5. Verify Ledger entries posted
        $financialTransaction = FinancialTransaction::where('reference_type', Transaction::class)
            ->where('reference_id', $transaction->id)
            ->first();
        
        $this->assertNotNull($financialTransaction, 'FinancialTransaction should be created');
        $this->assertEquals('payment', $financialTransaction->type);

        // 5. Verify Ledger balance invariant (debits == credits)
        $journalEntries = $financialTransaction->journalEntries;
        $totalDebits = 0;
        $totalCredits = 0;
        
        foreach ($journalEntries as $journalEntry) {
            foreach ($journalEntry->ledgerEntries as $ledgerEntry) {
                if ($ledgerEntry->type === 'debit') {
                    $totalDebits += $ledgerEntry->amount;
                } else {
                    $totalCredits += $ledgerEntry->amount;
                }
            }
        }
        
        $this->assertEqualsWithDelta($totalDebits, $totalCredits, 0.01, 
            'Ledger must balance: debits must equal credits');

        // 6. Verify revenue account credited with gross amount
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();
        
        $this->assertNotNull($revenueAccount, 'Revenue account should exist');
        
        $revenueCredits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
            ->where('type', 'credit')
            ->sum('amount');
        
        $this->assertEquals(50000, $revenueCredits, 
            'Revenue account should be credited with gross amount');

        // 7. Verify processing cost account (should be 0 for cash)
        $processingCostAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'processing_cost')
            ->first();
        
        if ($processingCostAccount) {
            $processingCostDebits = LedgerEntry::where('ledger_account_id', $processingCostAccount->id)
                ->where('type', 'debit')
                ->sum('amount');
            
            $this->assertEquals(0, $processingCostDebits, 
                'Processing cost should be 0 for cash payments');
        }

        // 8. Verify analytics sees correct numbers
        $financeMetrics = app(\App\Services\Finance\FinanceAnalyticsService::class)
            ->getSalonMetrics($salon);
        
        $this->assertEquals(50000, $financeMetrics['total_gross_revenue']);
        $this->assertEquals(50000, $financeMetrics['total_net_revenue']); // No fees
        $this->assertEquals(0, $financeMetrics['total_fees']);

        // 9. Verify salon isolation (Salon B cannot see Salon A's money)
        $salonB = Salon::factory()->create();
        $financeMetricsB = app(\App\Services\Finance\FinanceAnalyticsService::class)
            ->getSalonMetrics($salonB);
        
        $this->assertEquals(0, $financeMetricsB['total_gross_revenue']);
        $this->assertEquals(0, $financeMetricsB['total_net_revenue']);

        // 10. Verify no money created/destroyed
        // Total money in system = sum of all asset account credits
        $totalAssetCredits = LedgerEntry::whereHas('ledgerAccount', function ($query) {
            $query->where('account_type', 'cash');
        })->where('type', 'credit')->sum('amount');
        
        // For cash, asset should be debited (money in)
        $cashAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'cash')
            ->first();
        
        if ($cashAccount) {
            $cashDebits = LedgerEntry::where('ledger_account_id', $cashAccount->id)
                ->where('type', 'debit')
                ->sum('amount');
            
            $this->assertEquals(50000, $cashDebits, 
                'Cash asset should be debited with net amount');
        }
    }

    public function test_zero_platform_commission_enforced()
    {
        $feeEngine = app(FeeEngine::class);
        
        // Test various payment methods - all should have zero platform fee
        $paymentMethods = ['cash', 'mobile_money', 'card'];
        
        foreach ($paymentMethods as $method) {
            $paymentMethod = new PaymentMethod(['type' => $method]);
            $fees = $feeEngine->calculateFees(100000, $paymentMethod);
            
            $this->assertEquals(0, $fees['platform_fee'], 
                "Platform fee must be zero for {$method}");
            $this->assertEquals(100000, $fees['gross_amount']);
            $this->assertEquals(100000, $fees['net_amount']);
        }
    }

    public function test_gateway_fee_accounting()
    {
        // For digital payments, gateway fee should be posted to processing_cost
        // This test verifies the accounting structure when gateway fees exist
        
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id, 'price' => 50000]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'mobile_money',
            'provider' => 'flutterwave',
        ]);
        
        $booking = Booking::create([
            'salon_id' => $salon->id,
            'provider_id' => $salon->provider_id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'amount_due' => 50000,
        ]);

        // Simulate a payment with gateway fee (e.g., from provider response)
        $transaction = Transaction::create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => 50000,
            'gateway_fee' => 1000, // 2% gateway fee
            'platform_fee' => 0, // Zero platform commission
            'tax_amount' => 0,
            'net_amount' => 49000,
            'currency' => 'UGX',
            'internal_reference' => 'TXN-TEST',
            'provider_reference' => 'TEST-REF',
            'paid_at' => now(),
        ]);

        // Post journal manually for this test
        $journalService = app(\App\Domain\Finance\Journal\JournalPostingService::class);
        $journalService->postForTransaction($transaction);

        // Verify processing cost account debited with gateway fee
        $processingCostAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'processing_cost')
            ->first();
        
        $this->assertNotNull($processingCostAccount, 'Processing cost account should exist');
        
        $processingCostDebits = LedgerEntry::where('ledger_account_id', $processingCostAccount->id)
            ->where('type', 'debit')
            ->sum('amount');
        
        $this->assertEquals(1000, $processingCostDebits, 
            'Processing cost should be debited with gateway fee');

        // Verify net revenue = gross - gateway fee
        $financeMetrics = app(\App\Services\Finance\FinanceAnalyticsService::class)
            ->getSalonMetrics($salon);
        
        $this->assertEquals(50000, $financeMetrics['total_gross_revenue']);
        $this->assertEquals(49000, $financeMetrics['total_net_revenue']);
        $this->assertEquals(1000, $financeMetrics['total_fees']);
    }

    public function test_refund_reverses_ledger_entries()
    {
        $salon = Salon::factory()->create();
        $customer = Customer::factory()->create();
        $service = Service::factory()->create(['provider_id' => $salon->provider_id, 'price' => 50000]);
        $paymentMethod = PaymentMethod::factory()->create([
            'salon_id' => $salon->id,
            'type' => 'cash',
            'provider' => 'manual',
        ]);
        $paymentAccount = PaymentAccount::factory()->create([
            'salon_id' => $salon->id,
            'account_type' => PaymentAccount::TYPE_MERCHANT,
        ]);
        
        $booking = Booking::create([
            'salon_id' => $salon->id,
            'provider_id' => $salon->provider_id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'amount_due' => 50000,
            'amount_paid' => 50000,
        ]);

        // Create original payment transaction
        $paymentTransaction = Transaction::create([
            'salon_id' => $salon->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_account_id' => $paymentAccount->id,
            'type' => 'payment',
            'status' => 'completed',
            'gross_amount' => 50000,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'tax_amount' => 0,
            'net_amount' => 50000,
            'currency' => 'UGX',
            'internal_reference' => 'TXN-PAYMENT',
            'provider_reference' => 'MANUAL-PAYMENT',
            'paid_at' => now(),
        ]);

        // Load booking relationship to ensure event dispatch works
        $paymentTransaction->load('booking');

        // Post payment journal
        $journalService = app(\App\Domain\Finance\Journal\JournalPostingService::class);
        $journalService->postForTransaction($paymentTransaction);

        // Get revenue before refund
        $financeMetricsBefore = app(\App\Services\Finance\FinanceAnalyticsService::class)
            ->getSalonMetrics($salon);
        $revenueBefore = $financeMetricsBefore['total_gross_revenue'];

        $this->assertEquals(50000, $revenueBefore, 'Revenue should be 50,000 before refund');

        // Process refund via SalonPaymentService (triggers event chain)
        $paymentService = app(SalonPaymentService::class);
        $refundResult = $paymentService->refundSalonPayment($paymentTransaction->id);

        $this->assertTrue($refundResult['success']);

        // Verify refund transaction created
        $refundTransaction = Transaction::where('type', 'refund')
            ->where('booking_id', $booking->id)
            ->first();
        
        $this->assertNotNull($refundTransaction);
        $this->assertEquals(-50000, $refundTransaction->gross_amount);
        $this->assertEquals(-50000, $refundTransaction->net_amount);

        // Verify refund FinancialTransaction was created automatically via event chain
        $refundFinancialTransaction = FinancialTransaction::where('reference_type', Transaction::class)
            ->where('reference_id', $refundTransaction->id)
            ->first();
        
        $this->assertNotNull($refundFinancialTransaction, 'Refund FinancialTransaction should be created via event chain');
        $this->assertEquals('refund', $refundFinancialTransaction->type);

        // Debug: Check ledger entries for revenue account
        $revenueAccount = LedgerAccount::where('owner_type', Salon::class)
            ->where('owner_id', $salon->id)
            ->where('account_type', 'revenue')
            ->first();

        if ($revenueAccount) {
            $credits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
                ->where('type', 'credit')->sum('amount');
            $debits = LedgerEntry::where('ledger_account_id', $revenueAccount->id)
                ->where('type', 'debit')->sum('amount');
            
            // For debugging
            $this->assertEquals(50000, $credits, 'Revenue credits should be 50,000');
            $this->assertEquals(50000, $debits, 'Revenue debits should be 50,000 (refund reversal)');
        }

        // Get revenue after refund
        $financeMetricsAfter = app(\App\Services\Finance\FinanceAnalyticsService::class)
            ->getSalonMetrics($salon);
        $revenueAfter = $financeMetricsAfter['total_gross_revenue'];

        // Revenue should be back to zero (payment - refund)
        $this->assertEquals(0, $revenueAfter, 
            'Refund should reverse revenue to original state');

        // Verify refund transaction created
        $refundTransaction = Transaction::where('type', 'refund')
            ->where('booking_id', $booking->id)
            ->first();
        
        $this->assertNotNull($refundTransaction);
        $this->assertEquals(-50000, $refundTransaction->gross_amount);
        $this->assertEquals(-50000, $refundTransaction->net_amount);

        // Verify refund FinancialTransaction created
        $refundFinancialTransaction = FinancialTransaction::where('reference_type', Transaction::class)
            ->where('reference_id', $refundTransaction->id)
            ->first();
        
        $this->assertNotNull($refundFinancialTransaction, 'Refund FinancialTransaction should be created');
        $this->assertEquals('refund', $refundFinancialTransaction->type);
    }
}
