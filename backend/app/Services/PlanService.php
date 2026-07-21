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

        return [
            'current' => $currentPlan,
            'new' => $newPlan,
            'is_upgrade' => $newPlan->monthly_price > $currentPlan->monthly_price,
            'is_downgrade' => $newPlan->monthly_price < $currentPlan->monthly_price,
            'price_difference' => $newPlan->monthly_price - $currentPlan->monthly_price,
            'staff_change' => $newPlan->staff_limit - $currentPlan->staff_limit,
            'branches_change' => $newPlan->branches_limit - $currentPlan->branches_limit,
            'storage_change' => $newPlan->storage_limit_gb - $currentPlan->storage_limit_gb,
        ];
    }

    public function getPlanFeatures(string $planId): array
    {
        $plan = Plan::findOrFail($planId);
        return $plan->features ?? [];
    }
}
