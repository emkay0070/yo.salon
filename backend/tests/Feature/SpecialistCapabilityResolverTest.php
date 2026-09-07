<?php

namespace Tests\Feature;

use App\Models\Specialist;
use App\Models\SpecialistAccount;
use App\Models\SpecialistSubscription;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\BillingResource;
use App\Services\SpecialistCapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialistCapabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private SpecialistCapabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SpecialistCapabilityResolver();
    }

    /** @test */
    public function free_specialist_has_access_to_all_free_capabilities()
    {
        // Create a specialist without subscription (Free tier)
        $specialist = Specialist::factory()->create();

        // Free capabilities should be accessible
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

        foreach ($freeCapabilities as $capability) {
            $this->assertTrue(
                $this->resolver->allows($specialist->id, $capability),
                "Free specialist should have access to {$capability}"
            );
        }
    }

    /** @test */
    public function free_specialist_does_not_have_access_to_pro_capabilities()
    {
        // Create a specialist without subscription (Free tier)
        $specialist = Specialist::factory()->create();

        // Pro capabilities should NOT be accessible
        $proCapabilities = [
            'SPECIALIST_CAREER',
            'SPECIALIST_INTELLIGENCE',
            'SPECIALIST_FINANCE',
            'SPECIALIST_JOURNEY',
        ];

        foreach ($proCapabilities as $capability) {
            $this->assertFalse(
                $this->resolver->allows($specialist->id, $capability),
                "Free specialist should NOT have access to {$capability}"
            );
        }
    }

    /** @test */
    public function pro_specialist_has_access_to_all_capabilities()
    {
        // Create specialist and Pro subscription
        $specialist = Specialist::factory()->create();
        $proPlan = Plan::where('slug', 'specialist-pro')->firstOrFail();
        
        SpecialistSubscription::factory()->create([
            'specialist_id' => $specialist->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
        ]);

        // All capabilities should be accessible
        $allCapabilities = [
            'SPECIALIST_WORKSPACE',
            'SPECIALIST_CALENDAR',
            'SPECIALIST_APPOINTMENTS',
            'SPECIALIST_PROFILE',
            'SPECIALIST_CRAFT',
            'SPECIALIST_CLIENTS',
            'SPECIALIST_SETTINGS',
            'SPECIALIST_VERIFICATION',
            'SPECIALIST_CAREER',
            'SPECIALIST_INTELLIGENCE',
            'SPECIALIST_FINANCE',
            'SPECIALIST_JOURNEY',
        ];

        foreach ($allCapabilities as $capability) {
            $this->assertTrue(
                $this->resolver->allows($specialist->id, $capability),
                "Pro specialist should have access to {$capability}"
            );
        }
    }

    /** @test */
    public function cancelled_subscription_reverts_to_free_tier()
    {
        // Create specialist with cancelled subscription
        $specialist = Specialist::factory()->create();
        $proPlan = Plan::where('slug', 'specialist-pro')->firstOrFail();
        
        SpecialistSubscription::factory()->create([
            'specialist_id' => $specialist->id,
            'plan_id' => $proPlan->id,
            'status' => 'cancelled',
        ]);

        // Should behave like Free tier
        $this->assertFalse(
            $this->resolver->allows($specialist->id, 'SPECIALIST_INTELLIGENCE'),
            'Cancelled subscription should not have Pro access'
        );

        $this->assertTrue(
            $this->resolver->allows($specialist->id, 'SPECIALIST_CALENDAR'),
            'Cancelled subscription should still have Free capabilities'
        );
    }

    /** @test */
    public function trialing_specialist_has_pro_access()
    {
        // Create specialist with trial subscription
        $specialist = Specialist::factory()->create();
        $proPlan = Plan::where('slug', 'specialist-pro')->firstOrFail();
        
        SpecialistSubscription::factory()->create([
            'specialist_id' => $specialist->id,
            'plan_id' => $proPlan->id,
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Should have Pro access during trial
        $this->assertTrue(
            $this->resolver->allows($specialist->id, 'SPECIALIST_INTELLIGENCE'),
            'Trialing specialist should have Pro access'
        );
    }

    /** @test */
    public function get_capabilities_summary_returns_free_tier_for_no_subscription()
    {
        $specialist = Specialist::factory()->create();

        $summary = $this->resolver->getCapabilitiesSummary($specialist->id);

        $this->assertFalse($summary['has_subscription']);
        $this->assertEquals('specialist-free', $summary['plan']['slug']);
        $this->assertEquals('Free', $summary['plan']['name']);
        $this->assertFalse($summary['features']['SPECIALIST_INTELLIGENCE']);
        $this->assertTrue($summary['features']['SPECIALIST_CALENDAR']);
    }

    /** @test */
    public function get_capabilities_summary_returns_pro_tier_for_active_subscription()
    {
        $specialist = Specialist::factory()->create();
        $proPlan = Plan::where('slug', 'specialist-pro')->firstOrFail();
        
        SpecialistSubscription::factory()->create([
            'specialist_id' => $specialist->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
        ]);

        $summary = $this->resolver->getCapabilitiesSummary($specialist->id);

        $this->assertTrue($summary['has_subscription']);
        $this->assertEquals('specialist-pro', $summary['plan']['slug']);
        $this->assertEquals('Pro', $summary['plan']['name']);
        $this->assertTrue($summary['features']['SPECIALIST_INTELLIGENCE']);
        $this->assertTrue($summary['features']['SPECIALIST_CALENDAR']);
    }

    /** @test */
    public function cache_is_cleared_on_capability_change()
    {
        $specialist = Specialist::factory()->create();

        // First call should cache result
        $this->assertTrue($this->resolver->allows($specialist->id, 'SPECIALIST_CALENDAR'));

        // Clear cache
        $this->resolver->clearCache($specialist->id);

        // Should still work after cache clear
        $this->assertTrue($this->resolver->allows($specialist->id, 'SPECIALIST_CALENDAR'));
    }
}
