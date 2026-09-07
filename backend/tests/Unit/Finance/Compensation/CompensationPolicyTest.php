<?php

namespace Tests\Unit\Finance\Compensation;

use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Models\Salon;
use App\Models\Specialist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompensationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_for_category_resolution()
    {
        $policy = new CompensationPolicy([
            'rules' => [
                ['service_category' => 'haircut', 'rate' => 0.40],
                ['service_category' => 'coloring', 'rate' => 0.30],
                ['service_category' => '*', 'rate' => 0.25],
            ],
        ]);

        $this->assertEquals(0.40, $policy->ruleForCategory('haircut')['rate']);
        $this->assertEquals(0.30, $policy->ruleForCategory('coloring')['rate']);
        $this->assertEquals(0.25, $policy->ruleForCategory('treatment')['rate']); // Fallback
        $this->assertEquals(0.25, $policy->ruleForCategory(null)['rate']);      // Fallback
    }

    public function test_active_scope()
    {
        $salon = Salon::factory()->create();
        $specialist = Specialist::factory()->create();

        // Active
        CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $salon->id,
            'type' => 'commission',
            'rules' => [],
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        // Inactive flag
        CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $salon->id,
            'type' => 'commission',
            'rules' => [],
            'effective_from' => now()->subDay(),
            'is_active' => false,
        ]);

        // Future policy
        CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $salon->id,
            'type' => 'commission',
            'rules' => [],
            'effective_from' => now()->addDay(),
            'is_active' => true,
        ]);

        // Expired policy
        CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $specialist->id,
            'provider_type' => Salon::class,
            'provider_id' => $salon->id,
            'type' => 'commission',
            'rules' => [],
            'effective_from' => now()->subDays(10),
            'effective_until' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->assertEquals(1, CompensationPolicy::active()->count());
    }
}
