<?php

namespace App\Services;

use App\Models\Usage;
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UsageService
{
    public function __construct(
        private readonly CapabilityResolver $capabilityResolver
    ) {}

    public function recordUsage(string $subscriptionId, string $metric, int $value): Usage
    {
        return DB::transaction(function () use ($subscriptionId, $metric, $value) {
            $subscription = Subscription::findOrFail($subscriptionId);
            $salon = $subscription->salon;

            if (!$salon) {
                throw new \Exception('Salon not found for subscription');
            }

            // Get the effective limit from CapabilityResolver (plan + add-ons)
            $limit = $this->capabilityResolver->limit($salon->id, $metric);

            // Get or create usage record for current period
            $periodStart = Carbon::now()->startOfMonth();
            $periodEnd = Carbon::now()->endOfMonth();

            $usage = Usage::where('subscription_id', $subscriptionId)
                ->where('metric', $metric)
                ->where('period', 'current')
                ->where('period_start', $periodStart)
                ->first();

            if ($usage) {
                $usage->update([
                    'current_value' => $value,
                    'limit' => $limit,
                ]);
            } else {
                $usage = Usage::create([
                    'subscription_id' => $subscriptionId,
                    'metric' => $metric,
                    'current_value' => $value,
                    'limit' => $limit,
                    'period' => 'current',
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ]);
            }

            return $usage->fresh();
        });
    }

    public function getUsageBySubscription(string $subscriptionId): array
    {
        $usageRecords = Usage::where('subscription_id', $subscriptionId)
            ->current()
            ->get();

        return $usageRecords->map(function ($usage) {
            return [
                'metric' => $usage->metric,
                'current_value' => $usage->current_value,
                'limit' => $usage->limit,
                'remaining' => $usage->remaining,
                'percentage' => round($usage->percentage, 1),
                'is_near_limit' => $usage->isNearLimit(),
                'is_over_limit' => $usage->isOverLimit(),
            ];
        })->toArray();
    }

    public function getUsageByMetric(string $subscriptionId, string $metric): ?Usage
    {
        return Usage::where('subscription_id', $subscriptionId)
            ->where('metric', $metric)
            ->current()
            ->first();
    }

    public function incrementUsage(string $subscriptionId, string $metric, int $increment = 1): Usage
    {
        $usage = $this->getUsageByMetric($subscriptionId, $metric);

        if ($usage) {
            return $this->recordUsage($subscriptionId, $metric, $usage->current_value + $increment);
        }

        return $this->recordUsage($subscriptionId, $metric, $increment);
    }

    public function syncUsageWithActualData(string $subscriptionId): array
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $salon = $subscription->salon;

        if (!$salon) {
            // If no salon exists, return empty usage
            return $this->getUsageBySubscription($subscriptionId);
        }

        // Sync staff count using CapabilityResolver resource codes
        $staffCount = $salon->staff()->count();
        $this->recordUsage($subscriptionId, 'STAFF_SEAT', $staffCount);

        // Sync branches using CapabilityResolver resource codes
        $branchCount = $this->capabilityResolver->usage($salon->id, 'BRANCH');
        $this->recordUsage($subscriptionId, 'BRANCH', $branchCount);

        // Sync storage using CapabilityResolver resource codes
        $storageUsage = $this->capabilityResolver->usage($salon->id, 'STORAGE_GB');
        $this->recordUsage($subscriptionId, 'STORAGE_GB', $storageUsage);

        return $this->getUsageBySubscription($subscriptionId);
    }

    public function checkUsageLimits(string $subscriptionId): array
    {
        $usageData = $this->getUsageBySubscription($subscriptionId);
        $violations = [];

        foreach ($usageData as $metric) {
            if ($metric['is_over_limit']) {
                $violations[] = [
                    'metric' => $metric['metric'],
                    'current' => $metric['current_value'],
                    'limit' => $metric['limit'],
                    'exceeded_by' => $metric['current_value'] - $metric['limit'],
                ];
            }
        }

        return [
            'within_limits' => empty($violations),
            'violations' => $violations,
            'usage' => $usageData,
        ];
    }

    public function getUsageSummary(string $subscriptionId): array
    {
        $usageData = $this->getUsageBySubscription($subscriptionId);
        $subscription = Subscription::findOrFail($subscriptionId);

        return [
            'subscription_id' => $subscriptionId,
            'plan_name' => $subscription->plan->name,
            'billing_cycle' => $subscription->billing_cycle,
            'period_start' => Carbon::now()->startOfMonth()->toIso8601String(),
            'period_end' => Carbon::now()->endOfMonth()->toIso8601String(),
            'usage' => $usageData,
            'overall_health' => $this->calculateOverallHealth($usageData),
        ];
    }

    private function calculateOverallHealth(array $usageData): string
    {
        if (empty($usageData)) {
            return 'excellent';
        }

        $overLimit = collect($usageData)->filter(fn($u) => $u['is_over_limit'])->count();
        $nearLimit = collect($usageData)->filter(fn($u) => $u['is_near_limit'])->count();

        if ($overLimit > 0) {
            return 'critical';
        }

        if ($nearLimit > 0) {
            return 'warning';
        }

        return 'excellent';
    }
}
