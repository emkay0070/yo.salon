<?php

namespace App\Domain\Finance\Journal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * JournalEntry represents a group of related financial movements.
 * 
 * In accounting systems, journal entries group related ledger movements:
 * - Payment: Debit Customer, Credit Salon
 * - Commission: Debit Salon, Credit Specialist
 * - Refund: Debit Salon, Credit Customer
 * 
 * Journal provides audit trail and groups atomic financial operations.
 * Ledger entries are the individual movements within a journal.
 */
class JournalEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'financial_transaction_id',
        'journal_number',
        'type',
        'description',
        'reference_type',
        'reference_id',
        'total_debit',
        'total_credit',
        'status',
        'posted_at',
        'metadata',
    ];

    protected $casts = [
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The entity that caused this journal (Booking, Settlement, Payout, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The financial transaction this journal belongs to
     */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Finance\FinancialTransaction::class);
    }

    /**
     * All ledger entries in this journal
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(\App\Domain\Finance\Ledger\LedgerEntry::class, 'journal_entry_id');
    }

    /**
     * Create a journal entry with multiple ledger movements
     * 
     * This is the primary method for creating journal entries.
     * It validates that the journal balances (debits = credits)
     * and creates all ledger entries atomically.
     * 
     * BALANCE VALIDATION: A journal must always balance.
     * Total debits must equal total credits within 0.01 tolerance.
     * This is a fundamental accounting principle.
     * 
     * @param string $type - Journal type (payment, commission, settlement, refund, etc.)
     * @param string $description - Human-readable description
     * @param array $movements - Array of ledger movement configurations
     * @param string|null $referenceType - Type of entity that caused this
     * @param string|null $referenceId - ID of entity that caused this
     * @return self - The created JournalEntry
     * 
     * @throws \Exception If journal doesn't balance
     * @throws \Exception If ledger account not found
     */
    public static function createWithEntries(string $type, string $description, array $movements, ?string $referenceType = null, ?string $referenceId = null): self
    {
        $totalDebit = 0;
        $totalCredit = 0;

        // Calculate totals for balance validation
        foreach ($movements as $movement) {
            if ($movement['type'] === 'debit') {
                $totalDebit += $movement['amount'];
            } else {
                $totalCredit += $movement['amount'];
            }
        }

        // Validate journal balances
        // This prevents accounting errors and ensures data integrity
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \Exception('Journal entry must balance');
        }

        // Create the journal entry
        $journal = self::create([
            'journal_number' => self::generateJournalNumber(),
            'type' => $type,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        // Create ledger entries for each movement
        // Each movement represents a debit or credit to a specific account
        foreach ($movements as $movement) {
            $ledgerAccount = \App\Domain\Finance\Ledger\LedgerAccount::find($movement['ledger_account_id']);
            
            if (!$ledgerAccount) {
                throw new \Exception("Ledger account not found: {$movement['ledger_account_id']}");
            }

            // Calculate balance after this movement
            // Debit decreases balance, credit increases balance
            $balanceAfter = $movement['type'] === 'debit' 
                ? $ledgerAccount->balance - $movement['amount']
                : $ledgerAccount->balance + $movement['amount'];

            // Create the ledger entry
            // LedgerEntry has immutability guards - cannot be updated/deleted
            \App\Domain\Finance\Ledger\LedgerEntry::create([
                'journal_entry_id' => $journal->id,
                'ledger_account_id' => $movement['ledger_account_id'],
                'type' => $movement['type'],
                'amount' => $movement['amount'],
                'balance_after' => $balanceAfter,
                'description' => $movement['description'] ?? $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $movement['metadata'] ?? [],
            ]);

            // Update ledger account balance
            // Using increment/decrement for atomic operations
            if ($movement['type'] === 'debit') {
                $ledgerAccount->decrement('balance', $movement['amount']);
            } else {
                $ledgerAccount->increment('balance', $movement['amount']);
            }
        }

        return $journal;
    }

    /**
     * Generate unique journal number
     */
    protected static function generateJournalNumber(): string
    {
        return 'JNL-' . strtoupper(Str::random(10));
    }

    /**
     * Scope for posted journals
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope for draft journals
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for specific reference
     */
    public function scopeForReference($query, string $referenceType, string $referenceId)
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }

    /**
     * Check if journal is balanced
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    /**
     * Prevent updating journal entries (immutability guard)
     * 
     * IMMUTABILITY PRINCIPLE: Journals should never be edited after posting.
     * Corrections should be made via voiding and creating new journals.
     * 
     * EXCEPTION: Status changes are allowed (draft -> posted, posted -> voided)
     * This enables the journal lifecycle without compromising immutability.
     * 
     * @param array $attributes - Attributes to update
     * @param array $options - Update options
     * @return bool
     * @throws \Exception If attempting to update non-status fields
     */
    public function update(array $attributes = [], array $options = [])
    {
        // Allow status-only changes, or status + metadata combined (used by void())
        $allowedKeys = ['status', 'metadata'];
        $updateKeys = array_keys($attributes);
        $nonAllowed = array_diff($updateKeys, $allowedKeys);

        if (empty($nonAllowed) && isset($attributes['status'])) {
            return parent::update($attributes, $options);
        }

        throw new \Exception('Journal entries are immutable. Void and create correction journal instead.');
    }

    /**
     * Prevent deleting journal entries (immutability guard)
     * 
     * IMMUTABILITY PRINCIPLE: Journals should never be deleted.
     * Audit trails require complete history.
     * Corrections should be made via voiding and creating new journals.
     * 
     * @return bool
     * @throws \Exception Always throws exception
     */
    public function delete()
    {
        throw new \Exception('Journal entries are immutable. Void and create correction journal instead.');
    }

    /**
     * Void this journal (create reversing entries)
     * 
     * IMMUTABILITY PRINCIPLE: Never edit journals.
     * Instead, create a correction journal with reversing entries.
     * 
     * This method:
     * 1. Creates reversing movements (debits become credits, credits become debits)
     * 2. Creates a new journal with those reversing movements
     * 3. Updates account balances to original state
     * 4. Marks original journal as voided
     * 5. Dispatches event for audit trail
     * 
     * @throws \Exception If journal is already voided
     */
    public function void(): void
    {
        // Prevent double-voiding
        if ($this->status === 'voided') {
            throw new \Exception('Journal already voided');
        }

        // Build reversing movements
        // Each original entry gets a reversing entry with opposite type
        $reversingMovements = [];
        
        foreach ($this->ledgerEntries as $entry) {
            $reversingMovements[] = [
                'ledger_account_id' => $entry->ledger_account_id,
                'type' => $entry->type === 'debit' ? 'credit' : 'debit', // Flip debit/credit
                'amount' => $entry->amount,
                'description' => "Reversal of: {$entry->description}",
                'metadata' => [
                    'reversing_entry_id' => $entry->id,
                    'original_journal_number' => $this->journal_number,
                ],
            ];
        }

        // Create reversing journal
        // This journal will have opposite totals (debits become credits)
        $reversingJournal = self::create([
            'journal_number' => self::generateJournalNumber(),
            'type' => 'reversal',
            'description' => "Reversal of journal {$this->journal_number}",
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'total_debit' => $this->total_credit, // Swap totals
            'total_credit' => $this->total_debit, // Swap totals
            'status' => 'posted',
            'posted_at' => now(),
            'metadata' => [
                'reversing_journal_id' => $this->id,
                'original_journal_number' => $this->journal_number,
            ],
        ]);

        // Create reversing ledger entries
        // This restores account balances to their original state
        foreach ($reversingMovements as $movement) {
            $ledgerAccount = \App\Domain\Finance\Ledger\LedgerAccount::find($movement['ledger_account_id']);
            
            // Calculate balance after reversal
            $balanceAfter = $movement['type'] === 'debit' 
                ? $ledgerAccount->balance - $movement['amount']
                : $ledgerAccount->balance + $movement['amount'];

            \App\Domain\Finance\Ledger\LedgerEntry::create([
                'journal_entry_id' => $reversingJournal->id,
                'ledger_account_id' => $movement['ledger_account_id'],
                'type' => $movement['type'],
                'amount' => $movement['amount'],
                'balance_after' => $balanceAfter,
                'description' => $movement['description'],
                'reference_type' => $this->reference_type,
                'reference_id' => $this->reference_id,
                'metadata' => $movement['metadata'],
            ]);

            // Update ledger account balance
            // This should restore the balance to its original state
            if ($movement['type'] === 'debit') {
                $ledgerAccount->decrement('balance', $movement['amount']);
            } else {
                $ledgerAccount->increment('balance', $movement['amount']);
            }
        }

        // Mark original journal as voided
        // Only status changes are allowed (immutability guard)
        $this->update([
            'status' => 'voided',
            'metadata' => array_merge($this->metadata ?? [], [
                'voided_at' => now()->toISOString(),
                'voided_by_journal_id' => $reversingJournal->id,
            ]),
        ]);

        // Dispatch event for audit trail
        event(new \App\Domain\Finance\Events\JournalVoided(
            journalId: $this->id,
            journalNumber: $this->journal_number,
            reversingJournalId: $reversingJournal->id
        ));
    }
}
