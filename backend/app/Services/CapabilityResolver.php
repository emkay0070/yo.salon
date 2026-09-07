<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\PlanEntitlement;
use App\Models\SalonCapacity;
use App\Models\BillingResource;
use App\Models\Usage;
use App\Models\Salon;
use App\Models\Provider;
use Illuminate\Support\Facades\Cache;

/**
 * CapabilityResolver - Single authoritative brain for plan-based capabilities
 * 
 * This service is the ONLY place that should answer capability questions.
 * It combines:
 * - Plan entitlements (from subscription)
 * - Purchased add-on capacity (from SalonCapacity)
 * - Actual usage (from Usage/UsageService)
 * 
 * Effective capability = Plan entitlement + Purchased capacity - Consumed usage
 */
class CapabilityResolver
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if a salon has access to a specific feature.
     * 
     * @param string $salonId
     * @param string $featureCode e.g., 'CALENDAR_WEEK', 'CUSTOMER_PORTAL'
     * @return bool
     */
    public function allows(string $salonId, string $featureCode): bool
    {
        $cacheKey = "capability:allows:{$salonId}:{$featureCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($salonId, $featureCode) {
            $subscription = $this->getSubscriptionBySalon($salonId);
            
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
     * @param string $salonId
     * @param string $resourceCode e.g., 'STAFF_SEAT', 'BRANCH', 'STORAGE_GB'
     * @return int -1 for unlimited
     */
    public function limit(string $salonId, string $resourceCode): int
    {
        $cacheKey = "capability:limit:{$salonId}:{$resourceCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($salonId, $resourceCode) {
            $subscription = $this->getSubscriptionBySalon($salonId);
            
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
            $providerId = $subscription->provider_id;
            $additionalCapacity = SalonCapacity::active()
                ->forProvider($providerId)
                ->forResource($resourceCode)
                ->sum('additional_capacity');

            return $baseLimit + $additionalCapacity;
        });
    }

    /**
     * Get current usage for a resource from the source of truth.
     * 
     * @param string $salonId
     * @param string $resourceCode
     * @return int
     */
    public function usage(string $salonId, string $resourceCode): int
    {
        return match($resourceCode) {
            'STAFF_SEAT' => $this->getStaffCount($salonId),
            'BRANCH' => $this->getBranchCount($salonId),
            'STORAGE_GB' => $this->getStorageUsage($salonId),
            'SMS', 'AI_REQUEST', 'EMAIL', 'WHATSAPP' => $this->getCreditUsage($salonId, $resourceCode),
            default => 0,
        };
    }

    /**
     * Check if a salon can add more of a quota resource.
     * 
     * @param string $salonId
     * @param string $resourceCode
     * @return bool
     */
    public function canAdd(string $salonId, string $resourceCode): bool
    {
        $limit = $this->limit($salonId, $resourceCode);
        
        // Unlimited resources can always be added
        if ($limit === -1) {
            return true;
        }

        $currentUsage = $this->usage($salonId, $resourceCode);
        
        return $currentUsage < $limit;
    }

    /**
     * Get remaining capacity for a quota resource.
     * 
     * @param string $salonId
     * @param string $resourceCode
     * @return int -1 for unlimited
     */
    public function remaining(string $salonId, string $resourceCode): int
    {
        $limit = $this->limit($salonId, $resourceCode);
        
        if ($limit === -1) {
            return -1;
        }

        $currentUsage = $this->usage($salonId, $resourceCode);
        
        return max(0, $limit - $currentUsage);
    }

    /**
     * Get available credit balance.
     * 
     * @param string $salonId
     * @param string $creditCode e.g., 'SMS', 'AI_REQUEST'
     * @return int
     */
    public function credits(string $salonId, string $creditCode): int
    {
        $cacheKey = "capability:credits:{$salonId}:{$creditCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($salonId, $creditCode) {
            $subscription = $this->getSubscriptionBySalon($salonId);
            
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
            $providerId = $subscription->provider_id;
            $purchasedCredits = SalonCapacity::active()
                ->forProvider($providerId)
                ->forResource($creditCode)
                ->sum('additional_capacity');

            // Subtract consumed credits
            $consumedCredits = $this->getCreditUsage($salonId, $creditCode);

            return max(0, $includedCredits + $purchasedCredits - $consumedCredits);
        });
    }

    /**
     * Check if a salon is over limit for a resource (grandfathering state).
     * 
     * @param string $salonId
     * @param string $resourceCode
     * @return bool
     */
    public function isOverLimit(string $salonId, string $resourceCode): bool
    {
        $limit = $this->limit($salonId, $resourceCode);
        
        if ($limit === -1) {
            return false;
        }

        $currentUsage = $this->usage($salonId, $resourceCode);
        
        return $currentUsage > $limit;
    }

    /**
     * Get comprehensive capability summary for a salon.
     * Used by the /salons/{id}/capabilities API endpoint.
     * 
     * @param string $salonId
     * @return array
     */
    public function getCapabilitiesSummary(string $salonId): array
    {
        $subscription = $this->getSubscriptionBySalon($salonId);
        
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
                'used' => $this->usage($salonId, $resourceCode),
                'base_limit' => $this->getBaseLimit($subscription->plan_id, $resourceCode),
                'addon_limit' => $this->getAddonLimit($subscription->provider_id, $resourceCode),
                'effective_limit' => $this->limit($salonId, $resourceCode),
                'available' => $this->remaining($salonId, $resourceCode),
                'is_over_limit' => $this->isOverLimit($salonId, $resourceCode),
            ];
        }

        // Get credit resources (SMS, AI)
        $creditResources = ['SMS', 'AI_REQUEST'];
        $credits = [];
        foreach ($creditResources as $creditCode) {
            $credits[$creditCode] = [
                'included' => $this->getBaseLimit($subscription->plan_id, $creditCode),
                'purchased' => $this->getAddonLimit($subscription->provider_id, $creditCode),
                'consumed' => $this->getCreditUsage($salonId, $creditCode),
                'available' => $this->credits($salonId, $creditCode),
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
     * Clear capability cache for a salon (call after plan changes, add-on purchases, etc.)
     */
    public function clearCache(string $salonId): void
    {
        $pattern = "capability:*:{$salonId}:*";
        Cache::forgetMatchingPattern($pattern);
    }

    // ─────────────────────────────────────────────
    // Private helper methods
    // ─────────────────────────────────────────────

    private function getSubscriptionBySalon(string $salonId): ?Subscription
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return null;
        }

        return Subscription::where('provider_id', $salon->provider_id)->first();
    }

    private function getStaffCount(string $salonId): int
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return 0;
        }

        return $salon->staff()->count();
    }

    private function getBranchCount(string $salonId): int
    {
        // For now, always 1 until multi-branch is implemented
        return 1;
    }

    private function getStorageUsage(string $salonId): int
    {
        // TODO: Implement actual storage measurement
        return 0;
    }

    private function getCreditUsage(string $salonId, string $creditCode): int
    {
        $subscription = $this->getSubscriptionBySalon($salonId);
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
        return SalonCapacity::active()
            ->forProvider($providerId)
            ->forResource($resourceCode)
            ->sum('additional_capacity');
    }
}
