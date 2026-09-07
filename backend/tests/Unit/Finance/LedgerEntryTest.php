<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Domain\Finance\Ledger\LedgerEntry;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Models\Salon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LedgerEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_entry_is_immutable()
    {
        $salon = Salon::factory()->create();
        $account = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 1000,
        ]);

        $entry = LedgerEntry::create([
            'ledger_account_id' => $account->id,
            'type' => 'debit',
            'amount' => 100,
            'balance_after' => 900,
            'description' => 'Test entry',
        ]);

        // Attempting to update should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ledger entries are immutable');

        $entry->update(['amount' => 200]);
    }

    public function test_ledger_entry_cannot_be_deleted()
    {
        $salon = Salon::factory()->create();
        $account = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 1000,
        ]);

        $entry = LedgerEntry::create([
            'ledger_account_id' => $account->id,
            'type' => 'debit',
            'amount' => 100,
            'balance_after' => 900,
            'description' => 'Test entry',
        ]);

        // Attempting to delete should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ledger entries are immutable');

        $entry->delete();
    }

    public function test_ledger_entry_belongs_to_account()
    {
        $salon = Salon::factory()->create();
        $account = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 1000,
        ]);

        $entry = LedgerEntry::create([
            'ledger_account_id' => $account->id,
            'type' => 'debit',
            'amount' => 100,
            'balance_after' => 900,
            'description' => 'Test entry',
        ]);

        $this->assertEquals($account->id, $entry->ledger_account_id);
        $this->assertInstanceOf(LedgerAccount::class, $entry->ledgerAccount);
    }

    public function test_ledger_entry_scopes()
    {
        $salon = Salon::factory()->create();
        $account = LedgerAccount::create([
            'owner_type' => Salon::class,
            'owner_id' => $salon->id,
            'account_type' => 'revenue',
            'currency' => 'UGX',
            'balance' => 1000,
        ]);

        LedgerEntry::create([
            'ledger_account_id' => $account->id,
            'type' => 'debit',
            'amount' => 100,
            'balance_after' => 900,
            'description' => 'Debit entry',
        ]);

        LedgerEntry::create([
            'ledger_account_id' => $account->id,
            'type' => 'credit',
            'amount' => 50,
            'balance_after' => 950,
            'description' => 'Credit entry',
        ]);

        $debits = LedgerEntry::debits()->get();
        $credits = LedgerEntry::credits()->get();

        $this->assertCount(1, $debits);
        $this->assertCount(1, $credits);
        $this->assertEquals('debit', $debits->first()->type);
        $this->assertEquals('credit', $credits->first()->type);
    }
}
