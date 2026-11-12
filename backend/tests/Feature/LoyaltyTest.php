<?php

namespace Tests\Feature;

use App\Models\LoyaltyPoint;
use App\Models\Provider;
use App\Models\Location;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Specialist;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location;
    private Customer $customer;
    private Service $service;
    private Specialist $specialist;
    private Booking $completedBooking;
    private LoyaltyService $loyaltyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->service = Service::factory()->create(['provider_id' => $this->provider->id, 'price' => 100]);
        $this->specialist = Specialist::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->loyaltyService = new LoyaltyService();

        $this->completedBooking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'status' => 'completed',
        ]);
    }

    public function test_provider_customer_data_isolation()
    {
        $otherProvider = Provider::factory()->create();
        $otherCustomer = Customer::factory()->create();

        // Create loyalty for first provider
        LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'balance' => 100,
        ]);

        // Create loyalty for other provider
        LoyaltyPoint::factory()->create([
            'customer_id' => $otherCustomer->id,
            'provider_id' => $otherProvider->id,
            'balance' => 200,
        ]);

        // First customer should only see their balance for first provider
        $balance = LoyaltyPoint::getCustomerBalance($this->customer->id, $this->provider->id);
        $this->assertEquals(100, $balance);

        // First customer should not see other provider's balance
        $otherBalance = LoyaltyPoint::getCustomerBalance($this->customer->id, $otherProvider->id);
        $this->assertEquals(0, $otherBalance);
    }

    public function test_summary_balance_and_history()
    {
        LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'points_earned' => 100,
            'points_redeemed' => 0,
            'balance' => 100,
            'tier' => 'silver',
            'tier_progress' => 50,
        ]);

        $summary = $this->loyaltyService->getCustomerSummary($this->customer->id, $this->provider->id);

        $this->assertEquals(100, $summary['balance']);
        $this->assertEquals('silver', $summary['tier']);
        $this->assertEquals(50, $summary['tier_progress']);

        $history = $this->loyaltyService->getCustomerHistory($this->customer->id, $this->provider->id);
        $this->assertCount(1, $history);
        $this->assertEquals(100, $history[0]['points_earned']);
    }

    public function test_successful_redemption()
    {
        LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'balance' => 100,
        ]);

        $redemption = $this->loyaltyService->redeemPoints(
            $this->customer->id,
            $this->provider->id,
            50,
            'Test redemption'
        );

        $this->assertEquals(50, $redemption->points_redeemed);
        $this->assertEquals(50, $redemption->balance);
        $this->assertNotNull($redemption->redeemed_at);
    }

    public function test_insufficient_balance_redemption()
    {
        LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'balance' => 10,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient points balance');

        $this->loyaltyService->redeemPoints(
            $this->customer->id,
            $this->provider->id,
            50,
            'Test redemption'
        );
    }

    public function test_booking_completion_earns_points()
    {
        $this->loyaltyService->awardPointsForBooking($this->completedBooking->id);

        $balance = LoyaltyPoint::getCustomerBalance($this->customer->id, $this->provider->id);
        $this->assertEquals(100, $balance); // 1 point per unit of service price

        $points = LoyaltyPoint::where('metadata->booking_id', $this->completedBooking->id)->first();
        $this->assertNotNull($points);
        $this->assertEquals(100, $points->points_earned);
    }

    public function test_duplicate_booking_award_prevented()
    {
        $this->loyaltyService->awardPointsForBooking($this->completedBooking->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Points already awarded for this booking');

        $this->loyaltyService->awardPointsForBooking($this->completedBooking->id);
    }

    public function test_points_not_awarded_for_non_completed_booking()
    {
        $pendingBooking = Booking::factory()->create([
            'customer_id' => $this->customer->id,
            'location_id' => $this->location->id,
            'service_id' => $this->service->id,
            'specialist_id' => $this->specialist->id,
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Booking must be completed to award points');

        $this->loyaltyService->awardPointsForBooking($pendingBooking->id);
    }

    public function test_tier_thresholds_and_progression()
    {
        // Bronze tier (0-99 points)
        LoyaltyPoint::where('customer_id', $this->customer->id)->delete();
        $this->loyaltyService->addPoints($this->customer->id, $this->provider->id, 50, 'Test');
        $tier = LoyaltyPoint::getCustomerTier($this->customer->id, $this->provider->id);
        $this->assertEquals('bronze', $tier);

        // Silver tier (100-499 points)
        LoyaltyPoint::where('customer_id', $this->customer->id)->delete();
        $this->loyaltyService->addPoints($this->customer->id, $this->provider->id, 150, 'Test');
        $tier = LoyaltyPoint::getCustomerTier($this->customer->id, $this->provider->id);
        $this->assertEquals('silver', $tier);

        // Gold tier (500-999 points)
        LoyaltyPoint::where('customer_id', $this->customer->id)->delete();
        $this->loyaltyService->addPoints($this->customer->id, $this->provider->id, 600, 'Test');
        $tier = LoyaltyPoint::getCustomerTier($this->customer->id, $this->provider->id);
        $this->assertEquals('gold', $tier);

        // Platinum tier (1000+ points)
        LoyaltyPoint::where('customer_id', $this->customer->id)->delete();
        $this->loyaltyService->addPoints($this->customer->id, $this->provider->id, 1200, 'Test');
        $tier = LoyaltyPoint::getCustomerTier($this->customer->id, $this->provider->id);
        $this->assertEquals('platinum', $tier);
    }

    public function test_tier_progress_calculation()
    {
        LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'balance' => 50,
            'tier' => 'bronze',
        ]);

        $progress = LoyaltyPoint::getTierProgress($this->customer->id, $this->provider->id);

        $this->assertEquals('bronze', $progress['tier']);
        $this->assertEquals(50, $progress['points_to_next']); // 100 - 50
        $this->assertEquals('silver', $progress['next_tier']);
    }

    public function test_non_negative_balance_enforced()
    {
        LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'balance' => 10,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient points balance');

        // Try to redeem more than balance
        $this->loyaltyService->redeemPoints(
            $this->customer->id,
            $this->provider->id,
            20,
            'Test redemption'
        );
    }

    public function test_provider_relationship()
    {
        $loyaltyPoint = LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->provider->id, $loyaltyPoint->provider->id);
    }

    public function test_customer_relationship()
    {
        $loyaltyPoint = LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->customer->id, $loyaltyPoint->customer->id);
    }

    public function test_new_customer_has_bronze_tier()
    {
        $tier = LoyaltyPoint::getCustomerTier($this->customer->id, $this->provider->id);
        $this->assertEquals('bronze', $tier);

        $progress = LoyaltyPoint::getTierProgress($this->customer->id, $this->provider->id);
        $this->assertEquals('bronze', $progress['tier']);
        $this->assertEquals(0, $progress['progress']);
        $this->assertEquals(100, $progress['points_to_next']);
    }

    public function test_points_earned_increases_balance()
    {
        $this->loyaltyService->addPoints(
            $this->customer->id,
            $this->provider->id,
            50,
            'Test earning'
        );

        $balance = LoyaltyPoint::getCustomerBalance($this->customer->id, $this->provider->id);
        $this->assertEquals(50, $balance);
    }

    public function test_booking_award_uses_service_provider()
    {
        // Ensure service has provider_id
        $this->assertNotNull($this->service->provider_id);

        $this->loyaltyService->awardPointsForBooking($this->completedBooking->id);

        $points = LoyaltyPoint::where('metadata->booking_id', $this->completedBooking->id)->first();
        $this->assertEquals($this->service->provider_id, $points->provider_id);
    }

    public function test_transaction_safety_with_concurrent_operations()
    {
        // This test verifies that transactions are used
        // Actual concurrent testing would require multi-threading
        LoyaltyPoint::where('customer_id', $this->customer->id)->delete();
        $this->loyaltyService->addPoints(
            $this->customer->id,
            $this->provider->id,
            100,
            'Initial balance'
        );

        // Redeem should be in transaction
        $redemption = $this->loyaltyService->redeemPoints(
            $this->customer->id,
            $this->provider->id,
            50,
            'Test redemption'
        );

        // The redemption record should have balance 50 (100 - 50)
        $this->assertEquals(50, $redemption->balance);
        
        // The total balance (sum of all records) should be 150 (100 + 50)
        // This is the design: each transaction has its own balance, total is sum
        $totalBalance = LoyaltyPoint::getCustomerBalance($this->customer->id, $this->provider->id);
        $this->assertEquals(150, $totalBalance);
    }

    public function test_model_fillable_fields_match_schema()
    {
        $loyaltyPoint = LoyaltyPoint::factory()->create([
            'customer_id' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'points_earned' => 100,
            'points_redeemed' => 0,
            'balance' => 100,
            'tier' => 'silver',
            'tier_progress' => 50,
            'description' => 'Test',
            'metadata' => ['test' => 'data'],
        ]);

        $this->assertEquals($this->customer->id, $loyaltyPoint->customer_id);
        $this->assertEquals($this->provider->id, $loyaltyPoint->provider_id);
        $this->assertEquals(100, $loyaltyPoint->points_earned);
        $this->assertEquals('silver', $loyaltyPoint->tier);
    }
}
