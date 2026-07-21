<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Salon;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get wallet balance for customer at salon (calculated from transactions)
     */
    public function getBalance(string $customerId, string $salonId): float
    {
        $credits = Transaction::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount');

        $debits = Transaction::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount');

        return $credits - $debits;
    }

    /**
     * Add funds to customer wallet (create credit transaction)
     */
    public function addFunds(string $customerId, string $salonId, float $amount, string $description, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($customerId, $salonId, $amount, $description, $metadata) {
            // Get primary payment method for salon
            $paymentMethod = PaymentMethod::where('salon_id', $salonId)
                ->where('is_primary', true)
                ->where('is_active', true)
                ->first();

            if (!$paymentMethod) {
                throw new \Exception('No payment method configured for this salon');
            }

            // Create credit transaction
            $transaction = Transaction::create([
                'salon_id' => $salonId,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethod->id,
                'type' => 'credit',
                'status' => 'completed',
                'amount' => $amount,
                'internal_reference' => 'WALLET-' . strtoupper(uniqid()),
                'notes' => $description,
                'paid_at' => now(),
            ]);

            return $transaction;
        });
    }

    /**
     * Deduct funds from customer wallet (create debit transaction)
     */
    public function deductFunds(string $customerId, string $salonId, float $amount, string $description, array $metadata = []): Transaction
    {
        return DB::transaction(function () use ($customerId, $salonId, $amount, $description, $metadata) {
            $currentBalance = $this->getBalance($customerId, $salonId);

            if ($currentBalance < $amount) {
                throw new \Exception('Insufficient funds');
            }

            // Get primary payment method for salon
            $paymentMethod = PaymentMethod::where('salon_id', $salonId)
                ->where('is_primary', true)
                ->where('is_active', true)
                ->first();

            if (!$paymentMethod) {
                throw new \Exception('No payment method configured for this salon');
            }

            // Create debit transaction
            $transaction = Transaction::create([
                'salon_id' => $salonId,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethod->id,
                'type' => 'debit',
                'status' => 'completed',
                'amount' => $amount,
                'internal_reference' => 'WALLET-' . strtoupper(uniqid()),
                'notes' => $description,
                'paid_at' => now(),
            ]);

            return $transaction;
        });
    }

    /**
     * Get customer wallet details
     */
    public function getWallet(string $customerId, string $salonId): array
    {
        $balance = $this->getBalance($customerId, $salonId);
        
        // Get currency from salon's primary payment method
        $paymentMethod = PaymentMethod::where('salon_id', $salonId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();

        return [
            'balance' => $balance,
            'currency' => $paymentMethod ? $paymentMethod->currency : 'UGX',
        ];
    }

    /**
     * Get customer transactions
     */
    public function getTransactions(string $customerId, string $salonId, int $limit = 20): array
    {
        $transactions = Transaction::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->with('paymentMethod')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'notes' => $transaction->notes,
                'internal_reference' => $transaction->internal_reference,
                'created_at' => $transaction->created_at->toIso8601String(),
                'paid_at' => $transaction->paid_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get wallet summary for customer
     */
    public function getWalletSummary(string $customerId, string $salonId): array
    {
        $wallet = $this->getWallet($customerId, $salonId);
        $transactions = $this->getTransactions($customerId, $salonId, 5);

        return [
            'wallet' => $wallet,
            'recent_transactions' => $transactions,
        ];
    }
}
