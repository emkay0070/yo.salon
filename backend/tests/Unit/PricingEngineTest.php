<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Domain\Catalog\Engines\PricingEngine;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Models\LocationBookingConfig;
use App\Models\ProviderBookingConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->service = Service::factory()->create(['provider_id' => $this->provider->id, 'price' => 10000]);
        
        // Mock pivot variables normally set by LocationServiceCatalogResolver
        $this->service->pivot_price_override = null;
        $this->service->pivot_deposit_required_override = null;
    }

    public function test_location_payment_policy_override_wins()
    {
        ProviderBookingConfig::create([
            'provider_id' => $this->provider->id,
            'payment_policy' => [
                'strategy' => 'no_payment'
            ]
        ]);

        LocationBookingConfig::create([
            'location_id' => $this->location->id,
            'payment_policy_override' => [
                'strategy' => 'deposit',
                'deposit' => ['type' => 'percentage', 'amount' => 50, 'required_for' => 'all']
            ]
        ]);

        $engine = new PricingEngine($this->location, $this->service);
        $this->assertTrue($engine->requiresDeposit());
        $this->assertEquals(5000, $engine->depositAmount());
    }

    public function test_provider_policy_is_used_when_location_has_no_override()
    {
        ProviderBookingConfig::create([
            'provider_id' => $this->provider->id,
            'payment_policy' => [
                'strategy' => 'deposit',
                'deposit' => ['type' => 'percentage', 'amount' => 20, 'required_for' => 'all']
            ]
        ]);

        LocationBookingConfig::create([
            'location_id' => $this->location->id,
            'payment_policy_override' => null
        ]);

        $engine = new PricingEngine($this->location, $this->service);
        $this->assertTrue($engine->requiresDeposit());
        $this->assertEquals(2000, $engine->depositAmount());
    }

    public function test_default_policy_is_used_when_neither_exists()
    {
        $engine = new PricingEngine($this->location, $this->service);
        // Default policy is 'no_payment'
        $this->assertFalse($engine->requiresDeposit());
        $this->assertEquals(0, $engine->depositAmount());
    }

    public function test_percentage_deposit_calculation_works()
    {
        LocationBookingConfig::create([
            'location_id' => $this->location->id,
            'payment_policy_override' => [
                'strategy' => 'deposit',
                'deposit' => ['type' => 'percentage', 'amount' => 10, 'required_for' => 'all']
            ]
        ]);

        $engine = new PricingEngine($this->location, $this->service);
        $this->assertEquals(1000, $engine->depositAmount());
    }

    public function test_fixed_deposit_calculation_works()
    {
        LocationBookingConfig::create([
            'location_id' => $this->location->id,
            'payment_policy_override' => [
                'strategy' => 'deposit',
                'deposit' => ['type' => 'fixed', 'amount' => 1500, 'required_for' => 'all']
            ]
        ]);

        $engine = new PricingEngine($this->location, $this->service);
        $this->assertEquals(1500, $engine->depositAmount());
    }

    public function test_full_payment_works()
    {
        LocationBookingConfig::create([
            'location_id' => $this->location->id,
            'payment_policy_override' => [
                'strategy' => 'full_payment'
            ]
        ]);

        $engine = new PricingEngine($this->location, $this->service);
        $this->assertTrue($engine->requiresDeposit());
        $this->assertEquals(10000, $engine->depositAmount());
    }

    public function test_no_payment_works()
    {
        LocationBookingConfig::create([
            'location_id' => $this->location->id,
            'payment_policy_override' => [
                'strategy' => 'no_payment'
            ]
        ]);

        $engine = new PricingEngine($this->location, $this->service);
        $this->assertFalse($engine->requiresDeposit());
        $this->assertEquals(0, $engine->depositAmount());
    }
}
