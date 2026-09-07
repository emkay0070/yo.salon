<?php

namespace App\Domain\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * FinancialTransaction represents a business event that may generate multiple journals.
 * 
 * This is a wrapper for reconciliation. One business event = one financial transaction.
 * 
 * Example:
 * FinancialTransaction: Haircut #8247
 *   ├── Journal: Payment (Debit Customer, Credit Salon)
 *   ├── Journal: Commission (Debit Salon, Credit Specialist)
 *   └── Journal: Platform Fee (Debit Salon, Credit Platform)
 * 
 * This makes reconciliation easier. Instead of chasing multiple journals,
 * you open one transaction and see everything related to a business event.
 */
class FinancialTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_number',
        'type',
        'description',
        'reference_type',
        'reference_id',
        'total_amount',
        'currency',
        'status',
        'posted_at',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The entity that caused this transaction (Booking, Invoice, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * All journal entries in this transaction
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(\App\Domain\Finance\Journal\JournalEntry::class);
    }

    /**
     * Create a financial transaction with journals
     * 
     * This is the primary method for creating financial transactions.
     * It creates the transaction wrapper and all associated journal entries.
     * 
     * @param string $type - Transaction type (payment, refund, adjustment, etc.)
     * @param string $description - Human-readable description
     * @param array $journalData - Array of journal configurations
     * @param string|null $referenceType - Type of entity that caused this (Booking, Invoice, etc.)
     * @param string|null $referenceId - ID of entity that caused this
     * @return self - The created FinancialTransaction
     * 
     * @throws \Exception If journal creation fails
     */
    public static function createWithJournals(string $type, string $description, array $journalData, ?string $referenceType = null, ?string $referenceId = null): self
    {
        // Step 1: Create the financial transaction wrapper
        // This provides a single point of reference for reconciliation
        $transaction = self::create([
            'transaction_number' => self::generateTransactionNumber(),
            'type' => $type,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'total_amount' => 0, // Will be calculated from journals below
            'currency' => 'UGX',
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $totalAmount = 0;

        // Step 2: Create each journal entry
        // Each journal represents a group of related ledger movements
        foreach ($journalData as $journal) {
            // JournalEntry::createWithEntries handles balance validation
            // and creates all ledger entries atomically
            $journalEntry = \App\Domain\Finance\Journal\JournalEntry::createWithEntries(
                type: $journal['type'],
                description: $journal['description'],
                movements: $journal['movements'],
                referenceType: $referenceType,
                referenceId: $referenceId
            );

            // Associate journal with this transaction
            $journalEntry->financialTransaction()->associate($transaction);
            $journalEntry->save();

            // Accumulate total amount from journal credits
            $totalAmount += $journalEntry->total_credit;
        }

        // Step 3: Update transaction with calculated total
        $transaction->update(['total_amount' => $totalAmount]);

        // Step 4: Dispatch event for other domains to listen
        // This enables analytics, notifications, and audit trails
        event(new \App\Domain\Finance\Events\FinancialTransactionPosted(
            transactionId: $transaction->id,
            transactionNumber: $transaction->transaction_number,
            type: $type,
            totalAmount: $totalAmount,
            currency: 'UGX',
            referenceType: $referenceType,
            referenceId: $referenceId,
        ));

        return $transaction;
    }

    /**
     * Generate unique transaction number
     */
    protected static function generateTransactionNumber(): string
    {
        return 'FT-' . strtoupper(Str::random(10));
    }

    /**
     * Scope for posted transactions
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope for draft transactions
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
     * Void this transaction (create reversing journals)
     * 
     * IMMUTABILITY PRINCIPLE: Never edit financial records.
     * Instead, create reversing entries that undo the original transaction.
     * 
     * This method:
     * 1. Voids all associated journal entries (creates reversing journals)
     * 2. Marks the transaction as voided
     * 3. Dispatches an event for audit trail
     * 
     * @throws \Exception If transaction is already voided
     */
    public function void(): void
    {
        // Void each journal entry
        // JournalEntry::void() creates reversing journals with opposite entries
        foreach ($this->journalEntries as $journal) {
            $journal->void();
        }

        // Mark transaction as voided with audit metadata
        $this->update([
            'status' => 'voided',
            'metadata' => array_merge($this->metadata ?? [], [
                'voided_at' => now()->toISOString(),
                'voided_reason' => 'Transaction voided',
            ]),
        ]);

        // Dispatch event for audit trail and notifications
        event(new \App\Domain\Finance\Events\FinancialTransactionVoided(
            transactionId: $this->id,
            transactionNumber: $this->transaction_number
        ));
    }
}
