<?php

namespace App\Domain\Finance\Ledger;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * LedgerAccount represents a financial account for any entity in the platform.
 * 
 * This is a pure polymorphic account system. The Finance Domain doesn't care
 * about entity types - it only tracks financial movements between accounts.
 * 
 * Owner can be any entity:
 * - Customer
 * - Provider (Salon, Specialist, Supplier, etc.)
 * - Platform
 * - Employee
 * - Whatever future entities are added
 * 
 * No entity type logic in Finance Domain. Pure polymorphic relationships.
 */
class LedgerAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'account_type',
        'currency',
        'balance', // CACHED PROJECTION ONLY: The true balance is the sum of LedgerEntries. Do not mutate directly.
        'status',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * The entity that owns this account (Customer, Provider, Platform, etc.)
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * All ledger entries for this account
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Get the normal balance direction for this account type.
     * 
     * @return string 'debit' | 'credit'
     */
    public function getNormalBalanceAttribute(): string
    {
        $debitNormalTypes = [
            'cash', 'mobile_money', 'bank', 'receivable', 
            'processing_cost', 'expense', 'asset', 'revenue_allocation'
        ];

        return in_array($this->account_type, $debitNormalTypes) ? 'debit' : 'credit';
    }

    /**
     * Get the economic balance (user-facing balance).
     * 
     * Raw balances use Credit=+ and Debit=- mathematically.
     * For Debit-normal accounts (Assets, Expenses), a negative raw balance 
     * actually means a positive economic balance.
     */
    public function getEconomicBalanceAttribute(): float
    {
        if ($this->normal_balance === 'debit') {
            return -$this->balance;
        }

        return (float) $this->balance;
    }

    /**
     * Credit an account
     * Note: This is a helper. For double-entry, use JournalEntry::createWithEntries.
     */
    public function credit(float $amount, string $description, array $metadata = []): LedgerEntry
    {
        // If this account is debit-normal, crediting reduces its economic balance.
        if ($this->normal_balance === 'debit' && $this->economic_balance < $amount) {
            // Optional: throw exception if overdrafts aren't allowed
            // throw new \Exception('Insufficient economic balance');
        }

        $entry = LedgerEntry::create([
            'ledger_account_id' => $this->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $this->balance + $amount,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        $this->increment('balance', $amount);
        $this->refresh();

        return $entry;
    }

    /**
     * Debit an account
     * Note: This is a helper. For double-entry, use JournalEntry::createWithEntries.
     */
    public function debit(float $amount, string $description, array $metadata = []): LedgerEntry
    {
        // If this account is credit-normal, debiting reduces its economic balance.
        if ($this->normal_balance === 'credit' && $this->economic_balance < $amount) {
            // Optional: throw exception if overdrafts aren't allowed
            // throw new \Exception('Insufficient economic balance');
        }

        $entry = LedgerEntry::create([
            'ledger_account_id' => $this->id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $this->balance - $amount,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        $this->decrement('balance', $amount);
        $this->refresh();

        return $entry;
    }

    /**
     * Get or create account for an owner
     */
    public static function getOrCreateFor(string $ownerType, string $ownerId, string $accountType = 'default', string $currency = 'UGX'): self
    {
        return self::firstOrCreate(
            [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'account_type' => $accountType,
                'currency' => $currency,
            ],
            [
                'balance' => 0,
                'status' => 'active',
            ]
        );
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for specific owner
     */
    public function scopeForOwner($query, string $ownerType, string $ownerId)
    {
        return $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
    }
}
