<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\Provider;
use App\Models\Subscription;
use App\Services\CapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityResolverCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * clearCache() must evict feature, quota and credit answers using only
     * cache-store APIs that every driver supports.
     */
    public function test_clear_cache_evicts_stale_feature_quota_and_credit_answers(): void
    {
        $provider = Provider::factory()->create();

        $plan = Plan::create([
            'provider_type' => 'salon',
            'name' => 'Cache Test Plan',
            'slug' => 'cache-test-plan',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_active' => true,
        ]);

        $entitlements = [
            'CALENDAR_WEEK' => 1,
            'STAFF_SEAT' => 1,
            'SMS' => 10,
        ];

        foreach ($entitlements as $code => $limit) {
            PlanEntitlement::create([
                'plan_id' => $plan->id,
                'resource_code' => $code,
                'limit' => $limit,
                'reset_period' => 'never',
                'is_active' => true,
            ]);
        }

        Subscription::create([
            'provider_id' => $provider->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $resolver = app(CapabilityResolver::class);

        // Prime all three cache key families.
        $this->assertTrue($resolver->allows($provider->id, 'CALENDAR_WEEK'));
        $this->assertSame(1, $resolver->limit($provider->id, 'STAFF_SEAT'));
        $this->assertSame(10, $resolver->credits($provider->id, 'SMS'));

        // Change the plan while the old answers remain cached.
        PlanEntitlement::where('plan_id', $plan->id)
            ->where('resource_code', 'CALENDAR_WEEK')
            ->update(['limit' => 0]);
        PlanEntitlement::where('plan_id', $plan->id)
            ->where('resource_code', 'STAFF_SEAT')
            ->update(['limit' => 5]);
        PlanEntitlement::where('plan_id', $plan->id)
            ->where('resource_code', 'SMS')
            ->update(['limit' => 20]);

        $resolver->clearCache($provider->id);

        $this->assertFalse($resolver->allows($provider->id, 'CALENDAR_WEEK'));
        $this->assertSame(5, $resolver->limit($provider->id, 'STAFF_SEAT'));
        $this->assertSame(20, $resolver->credits($provider->id, 'SMS'));
    }
}
