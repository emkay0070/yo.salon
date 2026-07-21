<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Customer;
use App\Models\FeatureFlag;
use App\Models\FeaturePolicy;
use App\Models\Subscription;
use App\Models\Booking;
use App\Models\CustomerSalon;
use Illuminate\Support\Facades\DB;

class CapabilityService
{
    /**
     * Check if salon can use a feature
     */
    public function allows(Salon $salon, string $feature): bool
    {
        // Check if feature is explicitly enabled for this salon
        if (FeatureFlag::isEnabledForSalon($salon->id, $feature)) {
            return true;
        }

        // Check if feature should be auto-enabled based on policies
        $policies = FeaturePolicy::getActivePolicies($feature);
        
        foreach ($policies as $policy) {
            if ($this->evaluatePolicy($salon, $policy)) {
                // Auto-enable feature if policy conditions are met
                $this->enableFeature($salon, $feature, 'Auto-enabled based on policy: ' . $policy['description']);
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
        // Get customer's salon relationship
        $salonRelationship = $customer->getSalonRelationship($customer->salon_id ?? null);
        
        if (!$salonRelationship) {
            return false;
        }

        // Check if salon has the feature enabled
        $salon = Salon::find($salonRelationship->salon_id);
        if (!$salon) {
            return false;
        }

        // First check if salon allows the feature
        if (!$this->allows($salon, $feature)) {
            return false;
        }

        // Customer-specific checks based on feature
        return match($feature) {
            'loyalty' => $salonRelationship->visits >= 5,
            'packages' => $salonRelationship->visits >= 10,
            'gift_cards' => $salonRelationship->visits >= 15,
            'membership' => $salonRelationship->visits >= 20,
            default => true,
        };
    }

    /**
     * Get all available features for salon
     */
    public function getAvailableFeatures(Salon $salon): array
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
            if ($this->allows($salon, $feature)) {
                $available[] = $feature;
            }
        }

        return $available;
    }

    public function getCustomerCapabilities(Customer $customer): array
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
            'my_stylist',
            'rebook',
            'service_categories',
            'staff_profiles',
        ];

        $capabilities = [];
        foreach ($allFeatures as $feature) {
            $capabilities[$feature] = $this->allowsCustomer($customer, $feature);
        }

        return $capabilities;
    }

    /**
     * Enable feature for salon
     */
    public function enableFeature(Salon $salon, string $feature, string $reason = 'Manually enabled'): void
    {
        FeatureFlag::enableForSalon($salon->id, $feature, auth()->id(), $reason);
    }

    /**
     * Disable feature for salon
     */
    public function disableFeature(Salon $salon, string $feature): void
    {
        FeatureFlag::disableForSalon($salon->id, $feature);
    }

    /**
     * Check policy conditions
     */
    public function evaluatePolicy(Salon $salon, array $policy): bool
    {
        $policyType = $policy['policy_type'];
        $ruleValue = $policy['rule_value'];

        return match($policyType) {
            'subscription' => $this->evaluateSubscriptionPolicy($salon, $ruleValue),
            'customer_count' => $this->evaluateCustomerCountPolicy($salon, $ruleValue),
            'booking_count' => $this->evaluateBookingCountPolicy($salon, $ruleValue),
            'payments_enabled' => $this->evaluatePaymentsEnabledPolicy($salon, $ruleValue),
            'customer_history_exists' => $this->evaluateCustomerHistoryPolicy($salon, $ruleValue),
            default => false,
        };
    }

    /**
     * Suggest features based on salon metrics
     */
    public function suggestFeatures(Salon $salon): array
    {
        $suggestions = [];
        $metrics = $this->getSalonMetrics($salon);

        // Suggest loyalty if approaching customer threshold
        if (!$this->allows($salon, 'loyalty') && $metrics['customer_count'] >= 80) {
            $suggestions[] = [
                'feature' => 'loyalty',
                'reason' => "You have {$metrics['customer_count']} customers. Enable loyalty to reward repeat customers?",
                'confidence' => 'high',
            ];
        }

        // Suggest packages if approaching customer threshold
        if (!$this->allows($salon, 'packages') && $metrics['customer_count'] >= 150) {
            $suggestions[] = [
                'feature' => 'packages',
                'reason' => "You have {$metrics['customer_count']} customers. Offer service packages to increase revenue?",
                'confidence' => 'high',
            ];
        }

        // Suggest gift cards if payments enabled
        if (!$this->allows($salon, 'gift_cards') && $metrics['payments_enabled']) {
            $suggestions[] = [
                'feature' => 'gift_cards',
                'reason' => 'Enable gift cards to let customers share your services with others?',
                'confidence' => 'medium',
            ];
        }

        // Suggest AI recommendations if high booking volume
        if (!$this->allows($salon, 'ai_recommendations') && $metrics['booking_count'] >= 400) {
            $suggestions[] = [
                'feature' => 'ai_recommendations',
                'reason' => "You have {$metrics['booking_count']} bookings. Enable AI to personalize customer recommendations?",
                'confidence' => 'medium',
            ];
        }

        return $suggestions;
    }

    /**
     * Get salon metrics for policy evaluation
     */
    private function getSalonMetrics(Salon $salon): array
    {
        return [
            'customer_count' => CustomerSalon::where('salon_id', $salon->id)->count(),
            'booking_count' => Booking::where('salon_id', $salon->id)->count(),
            'payments_enabled' => $salon->paymentMethods()->exists(),
            'subscription_tier' => $this->getSubscriptionTier($salon),
        ];
    }

    /**
     * Evaluate subscription policy
     */
    private function evaluateSubscriptionPolicy(Salon $salon, array $ruleValue): bool
    {
        $tier = $this->getSubscriptionTier($salon);
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
    private function evaluateCustomerCountPolicy(Salon $salon, array $ruleValue): bool
    {
        $count = CustomerSalon::where('salon_id', $salon->id)->count();
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
    private function evaluateBookingCountPolicy(Salon $salon, array $ruleValue): bool
    {
        $count = Booking::where('salon_id', $salon->id)->count();
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
    private function evaluatePaymentsEnabledPolicy(Salon $salon, bool $ruleValue): bool
    {
        $paymentsEnabled = $salon->paymentMethods()->exists();
        return $paymentsEnabled === $ruleValue;
    }

    /**
     * Evaluate customer history policy
     */
    private function evaluateCustomerHistoryPolicy(Salon $salon, bool $ruleValue): bool
    {
        $hasHistory = Booking::where('salon_id', $salon->id)->exists();
        return $hasHistory === $ruleValue;
    }

    /**
     * Get subscription tier for salon
     */
    private function getSubscriptionTier(Salon $salon): ?string
    {
        $subscription = Subscription::where('salon_id', $salon->id)->first();
        if (!$subscription || !$subscription->isActive()) {
            return null;
        }

        $plan = $subscription->plan;
        return $plan ? $plan->name : null;
    }
}
