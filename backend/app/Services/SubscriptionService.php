<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Plan;
use App\Models\BillingEvent;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionService
{
    public function getSubscriptionBySalon(string $salonId): ?Subscription
    {
        return Subscription::where('salon_id', $salonId)->first();
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

    public function startTrial(string $salonId, string $planId, int $trialDays = 14): Subscription
    {
        return DB::transaction(function () use ($salonId, $planId, $trialDays) {
            $plan = Plan::findOrFail($planId);

            $subscription = Subscription::create([
                'salon_id' => $salonId,
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

            $subscription->update([
                'plan_id' => $newPlanId,
            ]);

            // Record billing event
            $this->recordBillingEvent($subscription->id, 'subscription_updated', 'Plan changed', [
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlanId,
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

    public function checkSubscriptionStatus(string $salonId): array
    {
        $subscription = $this->getSubscriptionBySalon($salonId);

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
}
