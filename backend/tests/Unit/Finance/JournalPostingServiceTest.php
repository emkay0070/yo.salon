<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Domain\Finance\Journal\JournalPostingService;
use App\Domain\Finance\PostingPolicies\PostingPolicyResolver;
use App\Domain\Finance\FinancialTransaction;
use App\Domain\Finance\Ledger\LedgerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JournalPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected JournalPostingService $service;
    protected Salon $salon;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new JournalPostingService(new PostingPolicyResolver());
        
        $this->salon = Salon::factory()->create();
    }

    public function test_cash_payment_posts_correct_journal()
    {
        $booking = Booking::factory()->create(['salon_id' => $this->salon->id, 'amount_due' => 30000]);
        $paymentMethod = PaymentMethod::factory()->create(['salon_id' => $this->salon->id, 'type' => 'cash']);
        
        $transaction = Transaction::factory()->create([
            'salon_id' => $this->salon->id,
            'booking_id' => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'payment',
            'gross_amount' => 30000,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 30000,
            'currency' => 'UGX',
        ]);

        $this->service->postForTransaction($transaction);

        $financialTransaction = FinancialTransaction::where('reference_type', Transaction::class)
            ->where('reference_id', $transaction->id)->first();
            
        $this->assertNotNull($financialTransaction);
        
        $journal = $financialTransaction->journalEntries->first();
        $this->assertEquals(30000, $journal->total_debit);
        $this->assertEquals(30000, $journal->total_credit);
        
        $this->assertDatabaseHas('ledger_entries', [
            'journal_entry_id' => $journal->id,
            'type' => 'debit',
            'amount' => 30000,
        ]);
        
        $this->assertDatabaseHas('ledger_entries', [
            'journal_entry_id' => $journal->id,
            'type' => 'credit',
            'amount' => 30000,
        ]);
        
        $cashAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'cash')->first();
        // Asset is debit-normal. Debit increases raw balance negatively, but economic balance should be positive.
        $this->assertEquals(-30000, $cashAccount->balance); 
        $this->assertEquals(30000, $cashAccount->economic_balance);
        
        $revenueAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'revenue')->first();
        // Revenue is credit-normal. Credit increases raw balance positively. Economic balance is the same.
        $this->assertEquals(30000, $revenueAccount->balance);
        $this->assertEquals(30000, $revenueAccount->economic_balance);
    }

    public function test_mobile_money_payment_posts_with_processing_cost()
    {
        $booking = Booking::factory()->create(['salon_id' => $this->salon->id, 'amount_due' => 30000]);
        $paymentMethod = PaymentMethod::factory()->create(['salon_id' => $this->salon->id, 'type' => 'mobile_money']);
        
        $transaction = Transaction::factory()->create([
            'salon_id' => $this->salon->id,
            'booking_id' => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'payment',
            'gross_amount' => 30000,
            'gateway_fee' => 840,
            'platform_fee' => 0,
            'net_amount' => 29160,
            'currency' => 'UGX',
        ]);

        $this->service->postForTransaction($transaction);

        $financialTransaction = FinancialTransaction::where('reference_type', Transaction::class)
            ->where('reference_id', $transaction->id)->first();
            
        $journal = $financialTransaction->journalEntries->first();
        $this->assertEquals(30000, $journal->total_debit);
        $this->assertEquals(30000, $journal->total_credit);
        
        $momoAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'mobile_money')->first();
        $this->assertEquals(-29160, $momoAccount->balance);
        $this->assertEquals(29160, $momoAccount->economic_balance);
        
        $processingCostAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'processing_cost')->first();
        $this->assertEquals(-840, $processingCostAccount->balance);
        $this->assertEquals(840, $processingCostAccount->economic_balance);
        
        $revenueAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'revenue')->first();
        $this->assertEquals(30000, $revenueAccount->balance);
        $this->assertEquals(30000, $revenueAccount->economic_balance);
    }

    public function test_partial_payment_posts_correct_amounts()
    {
        $booking = Booking::factory()->create(['salon_id' => $this->salon->id, 'amount_due' => 30000]);
        $paymentMethod = PaymentMethod::factory()->create(['salon_id' => $this->salon->id, 'type' => 'mobile_money']);
        
        $transaction = Transaction::factory()->create([
            'salon_id' => $this->salon->id,
            'booking_id' => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'payment',
            'gross_amount' => 15000, // Partial payment
            'gateway_fee' => 420,
            'platform_fee' => 0,
            'net_amount' => 14580,
            'currency' => 'UGX',
        ]);

        $this->service->postForTransaction($transaction);

        $momoAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'mobile_money')->first();
        $this->assertEquals(-14580, $momoAccount->balance);
        $this->assertEquals(14580, $momoAccount->economic_balance);
        
        $revenueAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'revenue')->first();
        $this->assertEquals(15000, $revenueAccount->balance);
        $this->assertEquals(15000, $revenueAccount->economic_balance);
    }

    public function test_refund_creates_reversal_journal()
    {
        $booking = Booking::factory()->create(['salon_id' => $this->salon->id, 'amount_due' => 30000]);
        $paymentMethod = PaymentMethod::factory()->create(['salon_id' => $this->salon->id, 'type' => 'mobile_money']);
        
        // Initial setup for existing balances
        $momoAccount = LedgerAccount::getOrCreateFor(Salon::class, $this->salon->id, 'mobile_money', 'UGX');
        $momoAccount->update(['balance' => -29160]); // Asset debited is negative
        
        $revenueAccount = LedgerAccount::getOrCreateFor(Salon::class, $this->salon->id, 'revenue', 'UGX');
        $revenueAccount->update(['balance' => 30000]); // Revenue credited is positive
        
        $processingAccount = LedgerAccount::getOrCreateFor(Salon::class, $this->salon->id, 'processing_cost', 'UGX');
        $processingAccount->update(['balance' => -840]); // Expense debited is negative
        
        $transaction = Transaction::factory()->create([
            'salon_id' => $this->salon->id,
            'booking_id' => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'refund',
            'gross_amount' => 30000,
            'gateway_fee' => 840,
            'platform_fee' => 0,
            'net_amount' => 29160,
            'currency' => 'UGX',
        ]);

        $this->service->postForTransaction($transaction);
        
        $momoAccount->refresh();
        $revenueAccount->refresh();
        $processingAccount->refresh();

        // Check reversed balances
        $this->assertEquals(0, $momoAccount->balance);
        $this->assertEquals(0, $revenueAccount->balance);
        $this->assertEquals(0, $processingAccount->balance);
    }

    public function test_idempotency_prevents_duplicate_posting()
    {
        $booking = Booking::factory()->create(['salon_id' => $this->salon->id, 'amount_due' => 30000]);
        $paymentMethod = PaymentMethod::factory()->create(['salon_id' => $this->salon->id, 'type' => 'cash']);
        
        $transaction = Transaction::factory()->create([
            'salon_id' => $this->salon->id,
            'booking_id' => $booking->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'payment',
            'gross_amount' => 30000,
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'net_amount' => 30000,
            'currency' => 'UGX',
        ]);

        $this->service->postForTransaction($transaction);
        
        // Ensure balances are updated once
        $revenueAccount = LedgerAccount::where('owner_id', $this->salon->id)->where('account_type', 'revenue')->first();
        $this->assertEquals(30000, $revenueAccount->balance);
        
        // Second call should be a no-op
        $this->service->postForTransaction($transaction);
        
        // Re-check balances
        $revenueAccount->refresh();
        $this->assertEquals(30000, $revenueAccount->balance); // Unchanged
        
        $this->assertEquals(1, FinancialTransaction::where('reference_type', Transaction::class)->where('reference_id', $transaction->id)->count());
    }
}
