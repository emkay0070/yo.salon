<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Models\Salon;
use Illuminate\Support\Facades\Log;

/**
 * PaymentRoutingService determines which PaymentAccount should receive funds.
 * 
 * This service implements the payment routing logic:
 * - PaymentMethod = HOW customer pays (MTN MoMo, Visa, Cash)
 * - PaymentAccount = WHOSE account receives money (Salon's Flutterwave, Yo.Salon routing)
 * 
 * Routing priority:
 * 1. PaymentMethod's explicit PaymentAccount (if set)
 * 2. Salon's default PaymentAccount for the provider
 * 3. Salon's default PaymentAccount (any provider)
 * 4. Fallback to null (for backward compatibility)
 */
class PaymentRoutingService
{
    /**
     * Determine the PaymentAccount for a given PaymentMethod.
     * 
     * @param PaymentMethod $paymentMethod
     * @param Salon|null $salon Optional salon context
     * @return PaymentAccount|null
     */
    public function determinePaymentAccount(PaymentMethod $paymentMethod, ?Salon $salon = null): ?PaymentAccount
    {
        // Priority 1: Use PaymentMethod's explicit PaymentAccount if set
        if ($paymentMethod->paymentAccount) {
            // Check if the account's provider matches the payment method's provider
            if ($paymentMethod->paymentAccount->provider === $paymentMethod->provider) {
                if ($this->isAccountUsable($paymentMethod->paymentAccount)) {
                    Log::debug('PaymentRouting: Using PaymentMethod explicit account', [
                        'payment_method_id' => $paymentMethod->id,
                        'payment_account_id' => $paymentMethod->paymentAccount->id,
                    ]);
                    return $paymentMethod->paymentAccount;
                }
            } else {
                Log::warning('PaymentRouting: Explicit account provider mismatch', [
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method_provider' => $paymentMethod->provider,
                    'payment_account_provider' => $paymentMethod->paymentAccount->provider,
                ]);
            }
        }

        // Get salon context from PaymentMethod if not provided
        $salon = $salon ?? $paymentMethod->salon;
        if (!$salon) {
            Log::warning('PaymentRouting: No salon context available', [
                'payment_method_id' => $paymentMethod->id,
            ]);
            return null;
        }

        // Priority 2: Use salon's default PaymentAccount for the provider
        $providerAccount = PaymentAccount::where('salon_id', $salon->id)
            ->where('provider', $paymentMethod->provider)
            ->where('is_default', true)
            ->active()
            ->verified()
            ->first();

        if ($providerAccount) {
            Log::debug('PaymentRouting: Using salon default account for provider', [
                'payment_method_id' => $paymentMethod->id,
                'provider' => $paymentMethod->provider,
                'payment_account_id' => $providerAccount->id,
            ]);
            return $providerAccount;
        }

        // Priority 3: Use salon's default PaymentAccount (any provider)
        $defaultAccount = PaymentAccount::where('salon_id', $salon->id)
            ->where('is_default', true)
            ->active()
            ->verified()
            ->first();

        if ($defaultAccount) {
            Log::debug('PaymentRouting: Using salon default account', [
                'payment_method_id' => $paymentMethod->id,
                'payment_account_id' => $defaultAccount->id,
            ]);
            return $defaultAccount;
        }

        // Priority 4: Return null for backward compatibility
        Log::info('PaymentRouting: No suitable PaymentAccount found, using null', [
            'payment_method_id' => $paymentMethod->id,
            'salon_id' => $salon->id,
        ]);
        return null;
    }

    /**
     * Determine the PaymentAccount for a manual payment (no PaymentMethod).
     * 
     * @param Salon $salon
     * @param string|null $provider Optional provider hint
     * @return PaymentAccount|null
     */
    public function determineAccountForManualPayment(Salon $salon, ?string $provider = null): ?PaymentAccount
    {
        $query = PaymentAccount::where('salon_id', $salon->id)
            ->active()
            ->verified();

        if ($provider) {
            $query->where('provider', $provider);
        }

        $account = $query->where('is_default', true)->first();

        if ($account) {
            Log::debug('PaymentRouting: Using default account for manual payment', [
                'salon_id' => $salon->id,
                'provider' => $provider,
                'payment_account_id' => $account->id,
            ]);
            return $account;
        }

        // Fallback to any active verified account
        $account = PaymentAccount::where('salon_id', $salon->id)
            ->active()
            ->verified()
            ->first();

        if ($account) {
            Log::debug('PaymentRouting: Using fallback account for manual payment', [
                'salon_id' => $salon->id,
                'payment_account_id' => $account->id,
            ]);
            return $account;
        }

        Log::info('PaymentRouting: No suitable PaymentAccount for manual payment', [
            'salon_id' => $salon->id,
        ]);
        return null;
    }

    /**
     * Check if a PaymentAccount is usable for receiving payments.
     * 
     * @param PaymentAccount $account
     * @return bool
     */
    protected function isAccountUsable(PaymentAccount $account): bool
    {
        return $account->canReceivePayments();
    }

    /**
     * Get all available PaymentAccounts for a salon.
     * 
     * @param Salon $salon
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableAccounts(Salon $salon)
    {
        return PaymentAccount::where('salon_id', $salon->id)
            ->active()
            ->verified()
            ->get();
    }

    /**
     * Get the default PaymentAccount for a salon.
     * 
     * @param Salon $salon
     * @return PaymentAccount|null
     */
    public function getDefaultAccount(Salon $salon): ?PaymentAccount
    {
        return PaymentAccount::where('salon_id', $salon->id)
            ->where('is_default', true)
            ->active()
            ->verified()
            ->first();
    }
}
