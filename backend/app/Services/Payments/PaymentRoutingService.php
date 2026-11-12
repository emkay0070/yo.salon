<?php

namespace App\Services\Payments;

use App\Models\PaymentMethod;
use App\Models\PaymentAccount;
use App\Models\Provider;
use Illuminate\Support\Facades\Log;

/**
 * PaymentRoutingService determines which PaymentAccount should receive funds.
 * 
 * This service implements the payment routing logic:
 * - PaymentMethod = HOW customer pays (MTN MoMo, Visa, Cash)
 * - PaymentAccount = WHOSE account receives money (Provider's Flutterwave, Yo.Salon routing)
 * 
 * Routing priority:
 * 1. PaymentMethod's explicit PaymentAccount (if set)
 * 2. Provider's default PaymentAccount for the provider
 * 3. Provider's default PaymentAccount (any provider)
 * 4. Fallback to null (for backward compatibility)
 */
class PaymentRoutingService
{
    /**
     * Determine the PaymentAccount for a given PaymentMethod.
     * 
     * @param PaymentMethod $paymentMethod
     * @param Provider|null $provider Optional provider context
     * @return PaymentAccount|null
     */
    public function determinePaymentAccount(PaymentMethod $paymentMethod, ?Provider $provider = null): ?PaymentAccount
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

        // Get provider context from PaymentMethod if not provided
        $provider = $provider ?? $paymentMethod->provider()->first();
        if (!$provider) {
            Log::warning('PaymentRouting: No provider context available', [
                'payment_method_id' => $paymentMethod->id,
            ]);
            return null;
        }

        // Priority 2: Use provider's default PaymentAccount for the payment provider
        $providerAccount = PaymentAccount::where('provider_id', $provider->id)
            ->where('provider', $paymentMethod->provider)
            ->where('is_default', true)
            ->active()
            ->verified()
            ->first();

        if ($providerAccount) {
            Log::debug('PaymentRouting: Using provider default account for payment provider', [
                'payment_method_id' => $paymentMethod->id,
                'provider' => $paymentMethod->provider,
                'payment_account_id' => $providerAccount->id,
            ]);
            return $providerAccount;
        }

        // Priority 3: Use provider's default PaymentAccount (any payment provider)
        $defaultAccount = PaymentAccount::where('provider_id', $provider->id)
            ->where('is_default', true)
            ->active()
            ->verified()
            ->first();

        if ($defaultAccount) {
            Log::debug('PaymentRouting: Using provider default account', [
                'payment_method_id' => $paymentMethod->id,
                'payment_account_id' => $defaultAccount->id,
            ]);
            return $defaultAccount;
        }

        // Priority 4: Return null for backward compatibility
        Log::info('PaymentRouting: No suitable PaymentAccount found, using null', [
            'payment_method_id' => $paymentMethod->id,
            'provider_id' => $provider->id,
        ]);
        return null;
    }

    /**
     * Determine the PaymentAccount for a manual payment (no PaymentMethod).
     * 
     * @param Provider $provider
     * @param string|null $paymentProvider Optional payment provider hint
     * @return PaymentAccount|null
     */
    public function determineAccountForManualPayment(Provider $provider, ?string $paymentProvider = null): ?PaymentAccount
    {
        $query = PaymentAccount::where('provider_id', $provider->id)
            ->active()
            ->verified();

        if ($paymentProvider) {
            $query->where('provider', $paymentProvider);
        }

        $account = $query->where('is_default', true)->first();

        if ($account) {
            Log::debug('PaymentRouting: Using default account for manual payment', [
                'provider_id' => $provider->id,
                'payment_provider' => $paymentProvider,
                'payment_account_id' => $account->id,
            ]);
            return $account;
        }

        // Fallback to any active verified account
        $account = PaymentAccount::where('provider_id', $provider->id)
            ->active()
            ->verified()
            ->first();

        if ($account) {
            Log::debug('PaymentRouting: Using fallback account for manual payment', [
                'provider_id' => $provider->id,
                'payment_account_id' => $account->id,
            ]);
            return $account;
        }

        Log::info('PaymentRouting: No suitable PaymentAccount for manual payment', [
            'provider_id' => $provider->id,
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
     * Get all available PaymentAccounts for a provider.
     * 
     * @param Provider $provider
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableAccounts(Provider $provider)
    {
        return PaymentAccount::where('provider_id', $provider->id)
            ->active()
            ->verified()
            ->get();
    }

    /**
     * Get the default PaymentAccount for a provider.
     * 
     * @param Provider $provider
     * @return PaymentAccount|null
     */
    public function getDefaultAccount(Provider $provider): ?PaymentAccount
    {
        return PaymentAccount::where('provider_id', $provider->id)
            ->where('is_default', true)
            ->active()
            ->verified()
            ->first();
    }
}
