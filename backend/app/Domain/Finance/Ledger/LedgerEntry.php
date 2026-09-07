<?php

namespace App\Domain\Finance\Ledger;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * LedgerEntry represents a single financial movement within a journal.
 * 
 * Every financial event creates journal entries, which contain ledger entries:
 * - Payment: Journal with debit/credit ledger entries
 * - Commission: Journal with debit/credit ledger entries
 * - Settlement: Journal with debit/credit ledger entries
 * 
 * Ledger entries are immutable business facts. Balances are derived from entries.
 * Journals group related movements for audit and atomic operations.
 */
class LedgerEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'journal_entry_id',
        'ledger_account_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * The journal entry this belongs to
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Finance\Journal\JournalEntry::class);
    }

    /**
     * The ledger account this entry belongs to
     */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /**
     * The entity that caused this entry (Booking, Settlement, Payout, etc.)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for credits (money in)
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope for debits (money out)
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope for specific reference
     */
    public function scopeForReference($query, string $referenceType, string $referenceId)
    {
        return $query->where('reference_type', $referenceType)->where('reference_id', $referenceId);
    }

    /**
     * Scope for date range
     */
    public function scopeBetween($query, string $start, string $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope for specific journal
     */
    public function scopeForJournal($query, string $journalId)
    {
        return $query->where('journal_entry_id', $journalId);
    }

    /**
     * Prevent updating ledger entries (immutability guard)
     * 
     * IMMUTABILITY PRINCIPLE: Ledger entries are immutable business facts.
     * Once created, they should never be changed.
     * 
     * This is how accounting systems survive audits.
     * If something is wrong, create reversing entries.
     * 
     * @param array $attributes - Attributes to update
     * @param array $options - Update options
     * @return bool
     * @throws \Exception Always throws exception
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('Ledger entries are immutable. Create reversing entries instead.');
    }

    /**
     * Prevent deleting ledger entries (immutability guard)
     * 
     * IMMUTABILITY PRINCIPLE: Ledger entries are immutable business facts.
     * Audit trails require complete history.
     * If something is wrong, create reversing entries.
     * 
     * @return bool
     * @throws \Exception Always throws exception
     */
    public function delete()
    {
        throw new \Exception('Ledger entries are immutable. Create reversing entries instead.');
    }
}
