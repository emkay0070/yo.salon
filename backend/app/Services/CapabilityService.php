<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\Customer;
use App\Models\FeatureFlag;
use App\Models\FeaturePolicy;
use App\Models\Subscription;
use App\Models\Booking;
use App\Models\CustomerSpecialist;
use Illuminate\Support\Facades\DB;

class CapabilityService
{
    /**
     * Check if provider can use a feature
     */
    public function allows(Provider $provider, string $feature): bool
    {
        // Check if feature is explicitly enabled for this provider
        if (FeatureFlag::isEnabledForProvider($provider->id, $feature)) {
            return true;
        }

        // Check if feature should be auto-enabled based on policies
        $policies = FeaturePolicy::getActivePolicies($feature);
        
        foreach ($policies as $policy) {
            if ($this->evaluatePolicy($provider, $policy)) {
                // Auto-enable feature if policy conditions are met
                $this->enableFeature($provider, $feature, 'Auto-enabled based on policy: ' . $policy['description']);
                return true;
            }
        }

        return false;
    }

    /**
     * Check if customer qualifies for portal feature
     */
    public function allowsCustomer(Customer $customer, string $feature): bool
    {
        // Get customer's provider relationship
        $providerRelationship = $customer->getProviderRelationship($customer->provider_id ?? null);
        
        if (!$providerRelationship) {
            return false;
        }

        // Check if provider has the feature enabled
        $provider = Provider::find($providerRelationship->provider_id);
        if (!$provider) {
            return false;
        }

        // First check if provider allows the feature
        if (!$this->allows($provider, $feature)) {
            return false;
        }

        // Customer-specific checks based on feature
        return match($feature) {
            'loyalty' => $providerRelationship->visits >= 5,
            'packages' => $providerRelationship->visits >= 10,
            'gift_cards' => $providerRelationship->visits >= 15,
            'membership' => $providerRelationship->visits >= 20,
            default => true,
        };
    }

    /**
     * Get all available features for provider
     */
    public function getAvailableFeatures(Provider $provider): array
    {
        $allFeatures = [
            'wallet',
            'loyalty',
            'gift_cards',
            'packages',
            'membership',
            'offers',
            'reviews',
            'support',
            'waitlist',
            'referrals',
            'subscriptions',
            'ai_recommendations',
            'my_stylist',
            'rebook',
            'service_categories',
            'staff_profiles',
        ];

        $available = [];
        foreach ($allFeatures as $feature) {
            if ($this->allows($provider, $feature)) {
                $available[] = $feature;
            }
        }

        return $available;
    }

    public function getCustomerCapabilities(Customer $customer, ?string $providerId = null): array
    {
        // If no providerId provided, use the first provider relationship
        if (!$providerId) {
            $firstProvider = $customer->providers()->first();
            $providerId = $firstProvider ? $firstProvider->id : null;
        }

        // Temporarily set provider_id on customer model for allowsCustomer
        $originalProviderId = $customer->provider_id ?? null;
        $customer->provider_id = $providerId;

        $allFeatures = [
            'wallet',
            'loyalty',
            'gift_cards',
            'packages',
            'membership',
            'offers',
            'reviews',
            'support',
            'waitlist',
            'referrals',
            'my_stylist',
            'rebook',
            'service_categories',
            'staff_profiles',
        ];

        $capabilities = [];
        foreach ($allFeatures as $feature) {
            $capabilities[$feature] = $this->allowsCustomer($customer, $feature);
        }

        // Restore original provider_id
        $customer->provider_id = $originalProviderId;

        return $capabilities;
    }

    /**
     * Enable feature for provider
     */
    public function enableFeature(Provider $provider, string $feature, string $reason = 'Manually enabled'): void
    {
        FeatureFlag::enableForProvider($provider->id, $feature, auth()->id(), $reason);
    }

    /**
     * Disable feature for provider
     */
    public function disableFeature(Provider $provider, string $feature): void
    {
        FeatureFlag::disableForProvider($provider->id, $feature);
    }

    /**
     * Check policy conditions
     */
    public function evaluatePolicy(Provider $provider, array $policy): bool
    {
        $policyType = $policy['policy_type'];
        $ruleValue = $policy['rule_value'];

        return match($policyType) {
            'subscription' => $this->evaluateSubscriptionPolicy($provider, $ruleValue),
            'customer_count' => $this->evaluateCustomerCountPolicy($provider, $ruleValue),
            'booking_count' => $this->evaluateBookingCountPolicy($provider, $ruleValue),
            'payments_enabled' => $this->evaluatePaymentsEnabledPolicy($provider, $ruleValue),
            'customer_history_exists' => $this->evaluateCustomerHistoryPolicy($provider, $ruleValue),
            default => false,
        };
    }

    /**
     * Suggest features based on provider metrics
     */
    public function suggestFeatures(Provider $provider): array
    {
        $suggestions = [];
        $metrics = $this->getProviderMetrics($provider);

        // Suggest loyalty if approaching customer threshold
        if (!$this->allows($provider, 'loyalty') && $metrics['customer_count'] >= 80) {
            $suggestions[] = [
                'feature' => 'loyalty',
                'reason' => "You have {$metrics['customer_count']} customers. Enable loyalty to reward repeat customers?",
                'confidence' => 'high',
            ];
        }

        // Suggest packages if approaching customer threshold
        if (!$this->allows($provider, 'packages') && $metrics['customer_count'] >= 150) {
            $suggestions[] = [
                'feature' => 'packages',
                'reason' => "You have {$metrics['customer_count']} customers. Offer service packages to increase revenue?",
                'confidence' => 'high',
            ];
        }

        // Suggest gift cards if payments enabled
        if (!$this->allows($provider, 'gift_cards') && $metrics['payments_enabled']) {
            $suggestions[] = [
                'feature' => 'gift_cards',
                'reason' => 'Enable gift cards to let customers share your services with others?',
                'confidence' => 'medium',
            ];
        }

        // Suggest AI recommendations if high booking volume
        if (!$this->allows($provider, 'ai_recommendations') && $metrics['booking_count'] >= 400) {
            $suggestions[] = [
                'feature' => 'ai_recommendations',
                'reason' => "You have {$metrics['booking_count']} bookings. Enable AI to personalize customer recommendations?",
                'confidence' => 'medium',
            ];
        }

        return $suggestions;
    }

    /**
     * Get provider metrics for policy evaluation
     */
    private function getProviderMetrics(Provider $provider): array
    {
        return [
            'customer_count' => CustomerSpecialist::where('provider_id', $provider->id)->count(),
            'booking_count' => Booking::where('provider_id', $provider->id)->count(),
            'payments_enabled' => $provider->paymentMethods()->exists(),
            'subscription_tier' => $this->getSubscriptionTier($provider),
        ];
    }

    /**
     * Evaluate subscription policy
     */
    private function evaluateSubscriptionPolicy(Provider $provider, array $ruleValue): bool
    {
        $tier = $this->getSubscriptionTier($provider);
        if (!$tier) {
            return false;
        }

        $operator = $ruleValue[0];
        $requiredTier = $ruleValue[1];

        $tiers = ['Starter' => 1, 'Professional' => 2, 'Premium' => 3];
        $currentLevel = $tiers[$tier] ?? 0;
        $requiredLevel = $tiers[$requiredTier] ?? 0;

        return match($operator) {
            '>=' => $currentLevel >= $requiredLevel,
            '>' => $currentLevel > $requiredLevel,
            '==' => $currentLevel === $requiredLevel,
            default => false,
        };
    }

    /**
     * Evaluate customer count policy
     */
    private function evaluateCustomerCountPolicy(Provider $provider, array $ruleValue): bool
    {
        $count = CustomerSpecialist::where('provider_id', $provider->id)->count();
        $operator = $ruleValue[0];
        $threshold = $ruleValue[1];

        return match($operator) {
            '>' => $count > $threshold,
            '>=' => $count >= $threshold,
            '==' => $count === $threshold,
            default => false,
        };
    }

    /**
     * Evaluate booking count policy
     */
    private function evaluateBookingCountPolicy(Provider $provider, array $ruleValue): bool
    {
        $count = Booking::where('provider_id', $provider->id)->count();
        $operator = $ruleValue[0];
        $threshold = $ruleValue[1];

        return match($operator) {
            '>' => $count > $threshold,
            '>=' => $count >= $threshold,
            '==' => $count === $threshold,
            default => false,
        };
    }

    /**
     * Evaluate payments enabled policy
     */
    private function evaluatePaymentsEnabledPolicy(Provider $provider, bool $ruleValue): bool
    {
        $paymentsEnabled = $provider->paymentMethods()->exists();
        return $paymentsEnabled === $ruleValue;
    }

    /**
     * Evaluate customer history policy
     */
    private function evaluateCustomerHistoryPolicy(Provider $provider, bool $ruleValue): bool
    {
        $hasHistory = Booking::where('provider_id', $provider->id)->exists();
        return $hasHistory === $ruleValue;
    }

    /**
     * Get subscription tier for provider
     */
    private function getSubscriptionTier(Provider $provider): ?string
    {
        $subscription = Subscription::where('provider_id', $provider->id)->first();
        if (!$subscription || !$subscription->isActive()) {
            return null;
        }

        $plan = $subscription->plan;
        return $plan ? $plan->name : null;
    }
}
