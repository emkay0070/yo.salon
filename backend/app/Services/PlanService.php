<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Collection;

class PlanService
{
    public function getAllActivePlans(): Collection
    {
        return Plan::active()->ordered()->get();
    }

    public function getPlanBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    public function getPlanById(string $id): ?Plan
    {
        return Plan::find($id);
    }

    public function createPlan(array $data): Plan
    {
        return Plan::create($data);
    }

    public function updatePlan(string $id, array $data): Plan
    {
        $plan = Plan::findOrFail($id);
        $plan->update($data);
        return $plan->fresh();
    }

    public function deletePlan(string $id): bool
    {
        $plan = Plan::findOrFail($id);
        return $plan->delete();
    }

    public function comparePlans(string $currentPlanId, string $newPlanId): array
    {
        $currentPlan = Plan::findOrFail($currentPlanId);
        $newPlan = Plan::findOrFail($newPlanId);

        // Get entitlement changes using PlanEntitlement system instead of direct plan fields
        $staffChange = $this->getEntitlementChange($currentPlanId, $newPlanId, 'STAFF_SEAT');
        $branchesChange = $this->getEntitlementChange($currentPlanId, $newPlanId, 'BRANCH');
        $storageChange = $this->getEntitlementChange($currentPlanId, $newPlanId, 'STORAGE_GB');

        return [
            'current' => $currentPlan,
            'new' => $newPlan,
            'is_upgrade' => $newPlan->monthly_price > $currentPlan->monthly_price,
            'is_downgrade' => $newPlan->monthly_price < $currentPlan->monthly_price,
            'price_difference' => $newPlan->monthly_price - $currentPlan->monthly_price,
            'staff_change' => $staffChange,
            'branches_change' => $branchesChange,
            'storage_change' => $storageChange,
        ];
    }

    /**
     * Get the change in entitlement limit between two plans for a specific resource.
     * Uses PlanEntitlement system instead of direct plan fields.
     */
    private function getEntitlementChange(string $currentPlanId, string $newPlanId, string $resourceCode): int
    {
        $currentEntitlement = \App\Models\PlanEntitlement::where('plan_id', $currentPlanId)
            ->where('resource_code', $resourceCode)
            ->where('is_active', true)
            ->first();

        $newEntitlement = \App\Models\PlanEntitlement::where('plan_id', $newPlanId)
            ->where('resource_code', $resourceCode)
            ->where('is_active', true)
            ->first();

        $currentLimit = $currentEntitlement ? $currentEntitlement->limit : 0;
        $newLimit = $newEntitlement ? $newEntitlement->limit : 0;

        return $newLimit - $currentLimit;
    }

    public function getPlanFeatures(string $planId): array
    {
        $plan = Plan::findOrFail($planId);
        return $plan->features ?? [];
    }
}
