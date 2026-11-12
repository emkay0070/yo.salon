<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\PlanEntitlement;
use App\Models\ProviderCapacity;
use App\Models\BillingResource;
use App\Models\Usage;
use App\Models\Provider;
use App\Models\Location;
use App\Models\SpecialistAssignment;
use Illuminate\Support\Facades\Cache;

/**
 * CapabilityResolver - Single authoritative brain for plan-based capabilities
 * 
 * This service is the ONLY place that should answer capability questions.
 * It combines:
 * - Plan entitlements (from subscription)
 * - Purchased add-on capacity (from ProviderCapacity)
 * - Actual usage (from Usage/UsageService)
 * 
 * Effective capability = Plan entitlement + Purchased capacity - Consumed usage
 */
class CapabilityResolver
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if a provider has access to a specific feature.
     * 
     * @param string $providerId
     * @param string $featureCode e.g., 'CALENDAR_WEEK', 'CUSTOMER_PORTAL'
     * @return bool
     */
    public function allows(string $providerId, string $featureCode): bool
    {
        $cacheKey = "capability:allows:{$providerId}:{$featureCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($providerId, $featureCode) {
            $subscription = $this->getSubscriptionByProvider($providerId);
            
            if (!$subscription) {
                return false;
            }

            // Check if feature is enabled in plan entitlements
            $entitlement = PlanEntitlement::where('plan_id', $subscription->plan_id)
                ->where('resource_code', $featureCode)
                ->where('is_active', true)
                ->first();

            // Features have limit = 1 (enabled) or 0 (disabled)
            return $entitlement && $entitlement->limit > 0;
        });
    }

    /**
     * Get the effective limit for a quota resource (staff, branches, storage).
     * 
     * @param string $providerId
     * @param string $resourceCode e.g., 'STAFF_SEAT', 'BRANCH', 'STORAGE_GB'
     * @return int -1 for unlimited
     */
    public function limit(string $providerId, string $resourceCode): int
    {
        $cacheKey = "capability:limit:{$providerId}:{$resourceCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($providerId, $resourceCode) {
            $subscription = $this->getSubscriptionByProvider($providerId);
            
            if (!$subscription) {
                return 0;
            }

            // Get base limit from plan entitlement
            $entitlement = PlanEntitlement::where('plan_id', $subscription->plan_id)
                ->where('resource_code', $resourceCode)
                ->where('is_active', true)
                ->first();

            $baseLimit = $entitlement ? $entitlement->limit : 0;

            // If base limit is unlimited, return immediately
            if ($baseLimit === -1) {
                return -1;
            }

            // Add purchased capacity from add-ons
            $additionalCapacity = ProviderCapacity::active()
                ->forProvider($providerId)
                ->forResource($resourceCode)
                ->sum('additional_capacity');

            return $baseLimit + $additionalCapacity;
        });
    }

    /**
     * Get current usage for a resource from the source of truth.
     * 
     * @param string $providerId
     * @param string $resourceCode
     * @return int
     */
    public function usage(string $providerId, string $resourceCode): int
    {
        return match($resourceCode) {
            'STAFF_SEAT' => $this->getStaffCount($providerId),
            'BRANCH' => $this->getBranchCount($providerId),
            'STORAGE_GB' => $this->getStorageUsage($providerId),
            'SMS', 'AI_REQUEST', 'EMAIL', 'WHATSAPP' => $this->getCreditUsage($providerId, $resourceCode),
            default => 0,
        };
    }

    /**
     * Check if a provider can add more of a quota resource.
     * 
     * @param string $providerId
     * @param string $resourceCode
     * @return bool
     */
    public function canAdd(string $providerId, string $resourceCode): bool
    {
        $limit = $this->limit($providerId, $resourceCode);
        
        // Unlimited resources can always be added
        if ($limit === -1) {
            return true;
        }

        $currentUsage = $this->usage($providerId, $resourceCode);
        
        return $currentUsage < $limit;
    }

    /**
     * Get remaining capacity for a quota resource.
     * 
     * @param string $providerId
     * @param string $resourceCode
     * @return int -1 for unlimited
     */
    public function remaining(string $providerId, string $resourceCode): int
    {
        $limit = $this->limit($providerId, $resourceCode);
        
        if ($limit === -1) {
            return -1;
        }

        $currentUsage = $this->usage($providerId, $resourceCode);
        
        return max(0, $limit - $currentUsage);
    }

    /**
     * Get available credit balance.
     * 
     * @param string $providerId
     * @param string $creditCode e.g., 'SMS', 'AI_REQUEST'
     * @return int
     */
    public function credits(string $providerId, string $creditCode): int
    {
        $cacheKey = "capability:credits:{$providerId}:{$creditCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($providerId, $creditCode) {
            $subscription = $this->getSubscriptionByProvider($providerId);
            
            if (!$subscription) {
                return 0;
            }

            // Get included credits from plan
            $entitlement = PlanEntitlement::where('plan_id', $subscription->plan_id)
                ->where('resource_code', $creditCode)
                ->where('is_active', true)
                ->first();

            $includedCredits = $entitlement ? $entitlement->limit : 0;

            // If unlimited, return immediately
            if ($includedCredits === -1) {
                return -1;
            }

            // Add purchased credits from add-ons
            $purchasedCredits = ProviderCapacity::active()
                ->forProvider($providerId)
                ->forResource($creditCode)
                ->sum('additional_capacity');

            // Subtract consumed credits
            $consumedCredits = $this->getCreditUsage($providerId, $creditCode);

            return max(0, $includedCredits + $purchasedCredits - $consumedCredits);
        });
    }

    /**
     * Check if a provider is over limit for a resource (grandfathering state).
     * 
     * @param string $providerId
     * @param string $resourceCode
     * @return bool
     */
    public function isOverLimit(string $providerId, string $resourceCode): bool
    {
        $limit = $this->limit($providerId, $resourceCode);
        
        if ($limit === -1) {
            return false;
        }

        $currentUsage = $this->usage($providerId, $resourceCode);
        
        return $currentUsage > $limit;
    }

    /**
     * Get comprehensive capability summary for a provider.
     * 
     * @param string $providerId
     * @return array
     */
    public function getCapabilitiesSummary(string $providerId): array
    {
        $subscription = $this->getSubscriptionByProvider($providerId);
        
        if (!$subscription) {
            return [
                'has_subscription' => false,
                'plan' => null,
                'features' => [],
                'resources' => [],
                'credits' => [],
            ];
        }

        $plan = $subscription->plan;

        // Get all feature entitlements
        $featureEntitlements = PlanEntitlement::where('plan_id', $plan->id)
            ->whereHas('resource', fn($q) => $q->where('type', 'feature'))
            ->where('is_active', true)
            ->get();

        $features = [];
        foreach ($featureEntitlements as $entitlement) {
            $features[$entitlement->resource_code] = $entitlement->limit > 0;
        }

        // Get quota resources (staff, branches, storage)
        $quotaResources = ['STAFF_SEAT', 'BRANCH', 'STORAGE_GB'];
        $resources = [];
        foreach ($quotaResources as $resourceCode) {
            $resources[$resourceCode] = [
                'used' => $this->usage($providerId, $resourceCode),
                'base_limit' => $this->getBaseLimit($subscription->plan_id, $resourceCode),
                'addon_limit' => $this->getAddonLimit($providerId, $resourceCode),
                'effective_limit' => $this->limit($providerId, $resourceCode),
                'available' => $this->remaining($providerId, $resourceCode),
                'is_over_limit' => $this->isOverLimit($providerId, $resourceCode),
            ];
        }

        // Get credit resources (SMS, AI)
        $creditResources = ['SMS', 'AI_REQUEST'];
        $credits = [];
        foreach ($creditResources as $creditCode) {
            $credits[$creditCode] = [
                'included' => $this->getBaseLimit($subscription->plan_id, $creditCode),
                'purchased' => $this->getAddonLimit($providerId, $creditCode),
                'consumed' => $this->getCreditUsage($providerId, $creditCode),
                'available' => $this->credits($providerId, $creditCode),
            ];
        }

        return [
            'has_subscription' => true,
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
            ],
            'subscription' => [
                'status' => $subscription->status,
                'is_trialing' => $subscription->isTrialing(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'renews_at' => $subscription->renews_at?->toIso8601String(),
            ],
            'features' => $features,
            'resources' => $resources,
            'credits' => $credits,
        ];
    }

    /**
     * Clear capability cache for a provider (call after plan changes, add-on purchases, etc.)
     */
    public function clearCache(string $providerId): void
    {
        $pattern = "capability:*:{$providerId}:*";
        Cache::forgetMatchingPattern($pattern);
    }

    // ─────────────────────────────────────────────
    // Private helper methods
    // ─────────────────────────────────────────────

    private function getSubscriptionByProvider(string $providerId): ?Subscription
    {
        return Subscription::where('provider_id', $providerId)->first();
    }

    private function getStaffCount(string $providerId): int
    {
        return SpecialistAssignment::where('provider_id', $providerId)
            ->where('status', 'ACTIVE')
            ->count();
    }

    private function getBranchCount(string $providerId): int
    {
        return Location::where('provider_id', $providerId)->count();
    }

    private function getStorageUsage(string $providerId): int
    {
        // TODO: Implement actual storage measurement
        return 0;
    }

    private function getCreditUsage(string $providerId, string $creditCode): int
    {
        $subscription = $this->getSubscriptionByProvider($providerId);
        if (!$subscription) {
            return 0;
        }

        $usage = Usage::where('subscription_id', $subscription->id)
            ->where('metric', strtolower($creditCode))
            ->current()
            ->first();

        return $usage ? $usage->current_value : 0;
    }

    private function getBaseLimit(string $planId, string $resourceCode): int
    {
        $entitlement = PlanEntitlement::where('plan_id', $planId)
            ->where('resource_code', $resourceCode)
            ->where('is_active', true)
            ->first();

        return $entitlement ? $entitlement->limit : 0;
    }

    private function getAddonLimit(string $providerId, string $resourceCode): int
    {
        return ProviderCapacity::active()
            ->forProvider($providerId)
            ->forResource($resourceCode)
            ->sum('additional_capacity');
    }
}
