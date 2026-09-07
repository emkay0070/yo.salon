<?php

namespace App\Services;

use App\Models\SpecialistSubscription;
use App\Models\PlanEntitlement;
use App\Models\Plan;
use App\Models\Specialist;
use Illuminate\Support\Facades\Cache;

/**
 * SpecialistCapabilityResolver - Single authoritative brain for specialist plan-based capabilities
 * 
 * This service is the ONLY place that should answer specialist capability questions.
 * It combines:
 * - Plan entitlements (from specialist subscription)
 * 
 * Effective capability = Plan entitlement
 * 
 * Free specialists have access to core professional identity tools by default.
 * Pro specialists have access to advanced business intelligence features.
 */
class SpecialistCapabilityResolver
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if a specialist has access to a specific feature.
     * 
     * @param string $specialistId
     * @param string $featureCode e.g., 'SPECIALIST_INTELLIGENCE', 'SPECIALIST_FINANCE'
     * @return bool
     */
    public function allows(string $specialistId, string $featureCode): bool
    {
        $cacheKey = "specialist_capability:allows:{$specialistId}:{$featureCode}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($specialistId, $featureCode) {
            $subscription = $this->getSubscriptionBySpecialist($specialistId);
            
            // If no subscription, use the default Free plan
            $plan = $subscription ? $subscription->plan : $this->getFreePlan();
            
            if (!$plan) {
                // Fallback: if no plan found, deny Pro features, allow core features
                $freeCapabilities = [
                    'SPECIALIST_WORKSPACE',
                    'SPECIALIST_CALENDAR',
                    'SPECIALIST_APPOINTMENTS',
                    'SPECIALIST_PROFILE',
                    'SPECIALIST_CRAFT',
                    'SPECIALIST_CLIENTS',
                    'SPECIALIST_SETTINGS',
                    'SPECIALIST_VERIFICATION',
                ];
                return in_array($featureCode, $freeCapabilities);
            }

            // Check if feature is enabled in plan entitlements
            $entitlement = PlanEntitlement::where('plan_id', $plan->id)
                ->where('resource_code', $featureCode)
                ->where('is_active', true)
                ->first();

            // Features have limit = 1 (enabled) or 0 (disabled)
            return $entitlement && $entitlement->limit > 0;
        });
    }

    /**
     * Get comprehensive capability summary for a specialist.
     * Used by the specialist portal API endpoint.
     * 
     * @param string $specialistId
     * @return array
     */
    public function getCapabilitiesSummary(string $specialistId): array
    {
        $subscription = $this->getSubscriptionBySpecialist($specialistId);
        
        // If no subscription, use the default Free plan
        $plan = $subscription ? $subscription->plan : $this->getFreePlan();
        
        if (!$plan) {
            // Fallback if no plan found at all
            return [
                'has_subscription' => false,
                'plan' => [
                    'id' => null,
                    'slug' => 'specialist-free',
                    'name' => 'Free',
                ],
                'subscription' => [
                    'status' => 'none',
                    'is_trialing' => false,
                    'trial_ends_at' => null,
                    'renews_at' => null,
                ],
                'features' => $this->getFreeCapabilities(),
            ];
        }

        // Get all feature entitlements from the plan
        $featureEntitlements = PlanEntitlement::where('plan_id', $plan->id)
            ->whereHas('resource', fn($q) => $q->where('type', 'feature'))
            ->where('is_active', true)
            ->get();

        $features = [];
        foreach ($featureEntitlements as $entitlement) {
            $features[$entitlement->resource_code] = $entitlement->limit > 0;
        }

        // Ensure all capability codes are present in the response
        $allCapabilities = array_merge(
            $this->getFreeCapabilities(),
            array_fill_keys([
                'SPECIALIST_CAREER',
                'SPECIALIST_INTELLIGENCE',
                'SPECIALIST_FINANCE',
                'SPECIALIST_JOURNEY',
            ], false)
        );

        // Merge with actual entitlements
        $features = array_merge($allCapabilities, $features);

        return [
            'has_subscription' => $subscription !== null,
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
            ],
            'subscription' => [
                'status' => $subscription ? $subscription->status : 'none',
                'is_trialing' => $subscription ? $subscription->isTrialing() : false,
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                'renews_at' => $subscription?->renews_at?->toIso8601String(),
            ],
            'features' => $features,
        ];
    }

    /**
     * Clear capability cache for a specialist (call after plan changes, subscription changes, etc.)
     */
    public function clearCache(string $specialistId): void
    {
        $pattern = "specialist_capability:*:{$specialistId}:*";
        Cache::forgetMatchingPattern($pattern);
    }

    /**
     * Get the specialist's current subscription.
     * Returns null if no active subscription exists (Free tier).
     */
    private function getSubscriptionBySpecialist(string $specialistId): ?SpecialistSubscription
    {
        return SpecialistSubscription::where('specialist_id', $specialistId)
            ->where('status', 'active')
            ->orWhere('status', 'trialing')
            ->first();
    }

    /**
     * Get the default Free plan for specialists.
     */
    private function getFreePlan(): ?Plan
    {
        return Plan::where('slug', 'specialist-free')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get free capabilities (always available to all specialists).
     */
    private function getFreeCapabilities(): array
    {
        return [
            'SPECIALIST_WORKSPACE' => true,
            'SPECIALIST_CALENDAR' => true,
            'SPECIALIST_APPOINTMENTS' => true,
            'SPECIALIST_PROFILE' => true,
            'SPECIALIST_CRAFT' => true,
            'SPECIALIST_CLIENTS' => true,
            'SPECIALIST_SETTINGS' => true,
            'SPECIALIST_VERIFICATION' => true,
            'SPECIALIST_CAREER' => false,
            'SPECIALIST_INTELLIGENCE' => false,
            'SPECIALIST_FINANCE' => false,
            'SPECIALIST_JOURNEY' => false,
        ];
    }
}
