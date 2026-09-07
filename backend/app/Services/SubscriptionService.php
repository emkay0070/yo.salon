<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\BillingEvent;
use App\Models\Provider;
use App\Services\CapabilityResolver;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * SubscriptionService - Provider-centric subscription management
 * 
 * This service manages subscriptions for all provider types (Salon, Specialist, etc.)
 * Subscriptions are provider-centric, not salon-centric.
 */
class SubscriptionService
{
    public function __construct(
        private readonly CapabilityResolver $capabilityResolver
    ) {}
    public function getSubscriptionByProvider(string $providerId): ?Subscription
    {
        return Subscription::where('provider_id', $providerId)->first();
    }

    public function getSubscriptionBySalon(string $salonId): ?Subscription
    {
        // Subscriptions are provider-centric, so we need to get the provider_id from the salon
        $salon = \App\Models\Salon::find($salonId);
        if (!$salon) {
            return null;
        }
        return $this->getSubscriptionByProvider($salon->provider_id);
    }

    public function createSubscription(array $data): Subscription
    {
        return DB::transaction(function () use ($data) {
            $subscription = Subscription::create($data);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_created', 'Subscription created', $data);

            return $subscription;
        });
    }

    public function startTrial(string $providerId, string $planId, int $trialDays = 14): Subscription
    {
        return DB::transaction(function () use ($providerId, $planId, $trialDays) {
            $plan = Plan::findOrFail($planId);

            $subscription = Subscription::create([
                'provider_id' => $providerId,
                'plan_id' => $planId,
                'status' => 'trialing',
                'billing_cycle' => 'monthly',
                'trial_ends_at' => Carbon::now()->addDays($trialDays),
                'starts_at' => Carbon::now(),
                'ends_at' => Carbon::now()->addDays($trialDays),
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'trial_started', 'Trial started', [
                'trial_days' => $trialDays,
                'plan_name' => $plan->name,
            ]);

            return $subscription;
        });
    }

    public function activateSubscription(string $subscriptionId): Subscription
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);
            $plan = $subscription->plan;

            $subscription->update([
                'status' => 'active',
                'trial_ends_at' => null,
            ]);

            // Set renewal date based on billing cycle
            $renewsAt = $subscription->billing_cycle === 'yearly'
                ? Carbon::now()->addYear()
                : Carbon::now()->addMonth();

            $subscription->update([
                'renews_at' => $renewsAt,
                'ends_at' => $renewsAt,
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_updated', 'Subscription activated', [
                'billing_cycle' => $subscription->billing_cycle,
                'renews_at' => $renewsAt->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    public function changePlan(string $subscriptionId, string $newPlanId): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $newPlanId) {
            $subscription = Subscription::findOrFail($subscriptionId);
            $oldPlanId = $subscription->plan_id;
            $salon = $subscription->salon;

            // Check for overage before changing plan
            if ($salon) {
                $overageDetected = $this->detectDowngradeOverage($subscription->id, $oldPlanId, $newPlanId, $salon->id);
                
                if ($overageDetected['has_overage']) {
                    // Mark subscription as over limit and grandfathered
                    $subscription->update([
                        'is_over_limit' => true,
                        'is_grandfathered' => true,
                        'grandfathering_metadata' => [
                            'detected_at' => Carbon::now()->toIso8601String(),
                            'old_plan_id' => $oldPlanId,
                            'new_plan_id' => $newPlanId,
                            'overage_resources' => $overageDetected['overage_resources'],
                        ],
                    ]);
                }
            }

            $subscription->update([
                'plan_id' => $newPlanId,
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_updated', 'Plan changed', [
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlanId,
                'overage_detected' => $overageDetected['has_overage'] ?? false,
            ]);

            return $subscription->fresh();
        });
    }

    public function cancelSubscription(string $subscriptionId, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $reason) {
            $subscription = Subscription::findOrFail($subscriptionId);

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now(),
                'cancel_reason' => $reason,
                'renews_at' => null,
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_cancelled', 'Subscription cancelled', [
                'reason' => $reason,
            ]);

            return $subscription->fresh();
        });
    }

    public function resumeSubscription(string $subscriptionId): Subscription
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);

            if (!$subscription->cancelled()) {
                throw new \Exception('Subscription is not cancelled');
            }

            $subscription->update([
                'status' => 'active',
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]);

            // Set renewal date
            $renewsAt = $subscription->billing_cycle === 'yearly'
                ? Carbon::now()->addYear()
                : Carbon::now()->addMonth();

            $subscription->update([
                'renews_at' => $renewsAt,
                'ends_at' => $renewsAt,
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_updated', 'Subscription resumed', [
                'renews_at' => $renewsAt->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    public function renewSubscription(string $subscriptionId): Subscription
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);

            if (!$subscription->isActive()) {
                throw new \Exception('Subscription is not active');
            }

            // Calculate new renewal date based on billing cycle
            $renewsAt = $subscription->billing_cycle === 'yearly'
                ? Carbon::now()->addYear()
                : Carbon::now()->addMonth();

            $subscription->update([
                'renews_at' => $renewsAt,
                'ends_at' => $renewsAt,
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_renewed', 'Subscription renewed', [
                'billing_cycle' => $subscription->billing_cycle,
                'renews_at' => $renewsAt->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    public function suspendSubscription(string $subscriptionId, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $reason) {
            $subscription = Subscription::findOrFail($subscriptionId);

            $subscription->update([
                'status' => 'past_due',
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_suspended', 'Subscription suspended', [
                'reason' => $reason,
            ]);

            return $subscription->fresh();
        });
    }

    public function checkSubscriptionStatus(string $providerId): array
    {
        $subscription = $this->getSubscriptionByProvider($providerId);

        if (!$subscription) {
            return [
                'has_subscription' => false,
                'status' => 'none',
            ];
        }

        return [
            'has_subscription' => true,
            'status' => $subscription->status,
            'is_active' => $subscription->isActive(),
            'is_trialing' => $subscription->isTrialing(),
            'on_trial' => $subscription->onTrial(),
            'cancelled' => $subscription->cancelled(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'renews_at' => $subscription->renews_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ];
    }

    private function recordBillingEvent(string $subscriptionId, string $type, string $description, array $payload = []): void
    {
        BillingEvent::create([
            'subscription_id' => $subscriptionId,
            'type' => $type,
            'description' => $description,
            'payload' => $payload,
        ]);
    }

    /**
     * Detect if a plan change would result in overage (downgrade scenario).
     * 
     * @param string $subscriptionId - The subscription ID
     * @param string $oldPlanId - The current plan ID
     * @param string $newPlanId - The new plan ID
     * @param string $salonId - The salon ID
     * @return array - Information about overage detection
     */
    private function detectDowngradeOverage(string $subscriptionId, string $oldPlanId, string $newPlanId, string $salonId): array
    {
        $overageResources = [];
        
        // Get quota resources to check
        $quotaResources = ['STAFF_SEAT', 'BRANCH', 'STORAGE_GB'];
        
        foreach ($quotaResources as $resourceCode) {
            // Get current usage
            $currentUsage = $this->capabilityResolver->usage($salonId, $resourceCode);
            
            // Get new plan limit (without add-ons for downgrade check)
            $newPlanLimit = $this->getPlanLimit($newPlanId, $resourceCode);
            
            // If new plan has lower limit and current usage exceeds it
            if ($newPlanLimit !== -1 && $currentUsage > $newPlanLimit) {
                $overageResources[] = [
                    'resource' => $resourceCode,
                    'current_usage' => $currentUsage,
                    'new_limit' => $newPlanLimit,
                    'overage_amount' => $currentUsage - $newPlanLimit,
                ];
            }
        }
        
        return [
            'has_overage' => count($overageResources) > 0,
            'overage_resources' => $overageResources,
        ];
    }

    /**
     * Get the base limit for a resource from a plan (excluding add-ons).
     * 
     * @param string $planId - The plan ID
     * @param string $resourceCode - The resource code
     * @return int - The limit (-1 for unlimited)
     */
    private function getPlanLimit(string $planId, string $resourceCode): int
    {
        $entitlement = \App\Models\PlanEntitlement::where('plan_id', $planId)
            ->where('resource_code', $resourceCode)
            ->where('is_active', true)
            ->first();

        return $entitlement ? $entitlement->limit : 0;
    }
}
