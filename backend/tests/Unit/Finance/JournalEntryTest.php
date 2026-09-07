<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Domain\Finance\Journal\JournalEntry;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Models\Salon;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_entry_must_balance()
    {
        $salon = Salon::factory()->create();
        $account1 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 0,
        ]);

        $account2 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'receivable',
            'currency' => 'UGX',
            'balance' => 0,
        ]);

        // Unbalanced journal should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal entry must balance');

        JournalEntry::createWithEntries(
            type: 'test',
            description: 'Test journal',
            movements: [
                [
                    'ledger_account_id' => $account1->id,
                    'type' => 'debit',
                    'amount' => 100,
                    'description' => 'Test debit',
                ],
                [
                    'ledger_account_id' => $account2->id,
                    'type' => 'credit',
                    'amount' => 50, // Not balanced
                    'description' => 'Test credit',
                ],
            ]
        );
    }

    public function test_journal_entry_creates_ledger_entries()
    {
        $salon = Salon::factory()->create();
        $account1 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 0,
        ]);

        $account2 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'receivable',
            'currency' => 'UGX',
            'balance' => 0,
        ]);

        $journal = JournalEntry::createWithEntries(
            type: 'test',
            description: 'Test journal',
            movements: [
                [
                    'ledger_account_id' => $account1->id,
                    'type' => 'debit',
                    'amount' => 100,
                    'description' => 'Test debit',
                ],
                [
                    'ledger_account_id' => $account2->id,
                    'type' => 'credit',
                    'amount' => 100,
                    'description' => 'Test credit',
                ],
            ]
        );

        $this->assertDatabaseHas('journal_entries', [
            'id' => $journal->id,
            'type' => 'test',
            'total_debit' => 100,
            'total_credit' => 100,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'journal_entry_id' => $journal->id,
            'ledger_account_id' => $account1->id,
            'type' => 'debit',
            'amount' => 100,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'journal_entry_id' => $journal->id,
            'ledger_account_id' => $account2->id,
            'type' => 'credit',
            'amount' => 100,
        ]);
    }

    public function test_journal_entry_updates_account_balances()
    {
        $salon = Salon::factory()->create();
        $account1 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 1000,
        ]);

        $account2 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'receivable',
            'currency' => 'UGX',
            'balance' => 500,
        ]);

        JournalEntry::createWithEntries(
            type: 'test',
            description: 'Test journal',
            movements: [
                [
                    'ledger_account_id' => $account1->id,
                    'type' => 'debit',
                    'amount' => 100,
                    'description' => 'Test debit',
                ],
                [
                    'ledger_account_id' => $account2->id,
                    'type' => 'credit',
                    'amount' => 100,
                    'description' => 'Test credit',
                ],
            ]
        );

        $account1->refresh();
        $account2->refresh();

        // Raw balance assertions — what the DB stores (Credit=+, Debit=-)
        $this->assertEquals(900, $account1->balance); // 1000 - 100 (revenue, debit reduces)
        $this->assertEquals(600, $account2->balance); // 500 + 100 (receivable, credit increases)

        // Economic balance assertions — what the number MEANS financially.
        // 'revenue' is credit-normal: raw balance = economic balance.
        // 'receivable' is debit-normal: raw balance is negative of economic balance.
        //
        // account1 (revenue, credit-normal): raw 900, economic 900
        $this->assertEquals(900.0, $account1->economic_balance,
            'Revenue (credit-normal) economic balance should equal raw balance');

        // account2 (receivable, debit-normal): we credited it (+100 raw from 500).
        // After a credit, raw = 600, but economic = -600 because receivable is debit-normal
        // and a credit reduces the outstanding receivable economically.
        // (If you debit a receivable you recognise the asset; crediting it reduces it.)
        $this->assertEquals(-600.0, $account2->economic_balance,
            'Receivable (debit-normal) economic balance should be negated raw balance');
    }

    public function test_journal_void_creates_reversing_entries()
    {
        $salon = Salon::factory()->create();
        $account1 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 1000,
        ]);

        $account2 = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'receivable',
            'currency' => 'UGX',
            'balance' => 500,
        ]);

        $journal = JournalEntry::createWithEntries(
            type: 'test',
            description: 'Test journal',
            movements: [
                [
                    'ledger_account_id' => $account1->id,
                    'type' => 'debit',
                    'amount' => 100,
                    'description' => 'Test debit',
                ],
                [
                    'ledger_account_id' => $account2->id,
                    'type' => 'credit',
                    'amount' => 100,
                    'description' => 'Test credit',
                ],
            ]
        );

        $journal->void();

        $journal->refresh();
        $this->assertEquals('voided', $journal->status);

        $account1->refresh();
        $account2->refresh();

        // Balances should be back to original
        $this->assertEquals(1000, $account1->balance);
        $this->assertEquals(500, $account2->balance);

        // Reversing journal should exist
        $this->assertDatabaseHas('journal_entries', [
            'type' => 'reversal',
            'total_debit' => 100,
            'total_credit' => 100,
        ]);
    }

    public function test_journal_entry_is_immutable()
    {
        $salon = Salon::factory()->create();
        $account = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 0,
        ]);

        $journal = JournalEntry::createWithEntries(
            type: 'test',
            description: 'Test journal',
            movements: [
                [
                    'ledger_account_id' => $account->id,
                    'type' => 'debit',
                    'amount' => 100,
                    'description' => 'Test debit',
                ],
                [
                    'ledger_account_id' => $account->id,
                    'type' => 'credit',
                    'amount' => 100,
                    'description' => 'Test credit',
                ],
            ]
        );

        // Attempting to update should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal entries are immutable');

        $journal->update(['description' => 'Updated description']);
    }

    public function test_journal_entry_status_change_allowed()
    {
        $salon = Salon::factory()->create();
        $account = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 0,
        ]);

        $journal = JournalEntry::createWithEntries(
            type: 'test',
            description: 'Test journal',
            movements: [
                [
                    'ledger_account_id' => $account->id,
                    'type' => 'debit',
                    'amount' => 100,
                    'description' => 'Test debit',
                ],
                [
                    'ledger_account_id' => $account->id,
                    'type' => 'credit',
                    'amount' => 100,
                    'description' => 'Test credit',
                ],
            ]
        );

        // Status changes should be allowed
        $journal->update(['status' => 'voided']);
        $journal->refresh();

        $this->assertEquals('voided', $journal->status);
    }
}
