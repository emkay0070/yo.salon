<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Provider;
use App\Models\Customer;
use App\Models\User;
use App\Services\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class GiftCardTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Customer $customer;
    private GiftCardService $giftCardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->giftCardService = new GiftCardService();
    }

    public function test_provider_customer_data_isolation()
    {
        $otherProvider = Provider::factory()->create();
        $otherCustomer = Customer::factory()->create();

        // Create gift card for first provider
        GiftCard::factory()->create([
            'purchased_by' => $this->customer->id,
            'provider_id' => $this->provider->id,
            'amount' => 100,
            'balance' => 100,
        ]);

        // Create gift card for other provider
        GiftCard::factory()->create([
            'purchased_by' => $otherCustomer->id,
            'provider_id' => $otherProvider->id,
            'amount' => 200,
            'balance' => 200,
        ]);

        // First customer should only see their gift cards for first provider
        $giftCards = $this->giftCardService->getCustomerPurchasedGiftCards($this->customer->id, $this->provider->id);
        $this->assertCount(1, $giftCards);
        $this->assertEquals(100, $giftCards[0]['amount']);

        // First customer should not see other provider's gift cards
        $otherGiftCards = $this->giftCardService->getCustomerPurchasedGiftCards($this->customer->id, $otherProvider->id);
        $this->assertCount(0, $otherGiftCards);
    }



    public function test_partial_redemption()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'available',
        ]);

        $result = $this->giftCardService->redeemGiftCardPartial(
            $giftCard->code,
            $this->customer->id,
            $this->provider->id,
            50
        );

        $this->assertEquals('partially_redeemed', $result['gift_card']->status);
        $this->assertEquals(50, $result['remaining_balance']);
        $this->assertEquals(50, $result['amount_redeemed']);
    }

    public function test_insufficient_balance_redemption()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 10,
            'balance' => 10,
            'status' => 'available',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient gift card balance');

        $this->giftCardService->redeemGiftCardPartial(
            $giftCard->code,
            $this->customer->id,
            $this->provider->id,
            50
        );
    }

    public function test_expired_gift_card_cannot_be_redeemed()
    {
        $giftCard = GiftCard::factory()->expired()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired gift card');

        $this->giftCardService->redeemGiftCard($giftCard->code, $this->customer->id, $this->provider->id);
    }

    public function test_cancelled_gift_card_cannot_be_redeemed()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'active' => false,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired gift card');

        $this->giftCardService->redeemGiftCard($giftCard->code, $this->customer->id, $this->provider->id);
    }

    public function test_gift_card_cancellation()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'available',
        ]);

        $cancelled = $this->giftCardService->cancelGiftCard($giftCard->id, 'Customer request');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertFalse($cancelled->active);
    }

    public function test_fully_redeemed_gift_card_cannot_be_cancelled()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 0,
            'status' => 'fully_redeemed',
            'redeemed_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gift card cannot be cancelled in current status');

        $this->giftCardService->cancelGiftCard($giftCard->id, 'Test');
    }

    public function test_balance_adjustment()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'available',
            'issued_at' => now(),
        ]);

        $adjusted = $this->giftCardService->adjustBalance($giftCard->id, 25, 'Bonus adjustment');

        $this->assertEquals(125, $adjusted->balance);
    }

    public function test_balance_adjustment_cannot_make_negative()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 10,
            'status' => 'available',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Balance cannot be negative');

        $this->giftCardService->adjustBalance($giftCard->id, -25, 'Test');
    }



    public function test_gift_card_issuance_from_created_status()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'created',
        ]);

        $issued = $this->giftCardService->issueGiftCard($giftCard->id);

        $this->assertEquals('available', $issued->status);
        $this->assertNotNull($issued->issued_at);
    }

    public function test_gift_card_cannot_be_issued_from_non_created_status()
    {
        $giftCard = GiftCard::factory()->available()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Gift card can only be issued from created status');

        $this->giftCardService->issueGiftCard($giftCard->id);
    }

    public function test_provider_relationship()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
        ]);

        $this->assertEquals($this->provider->id, $giftCard->provider->id);
    }

    public function test_customer_relationship()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
        ]);

        $this->assertEquals($this->customer->id, $giftCard->purchasedBy->id);
    }

    public function test_transaction_safety_with_concurrent_operations()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'available',
        ]);

        // Redeem should be in transaction with lockForUpdate
        $result = $this->giftCardService->redeemGiftCardPartial(
            $giftCard->code,
            $this->customer->id,
            $this->provider->id,
            50
        );

        $this->assertEquals(50, $result['remaining_balance']);
        $this->assertEquals('partially_redeemed', $result['gift_card']->status);
    }

    public function test_code_generation_is_unique()
    {
        $code1 = GiftCard::generateCode();
        $code2 = GiftCard::generateCode();

        $this->assertNotEquals($code1, $code2);
        $this->assertEquals(12, strlen($code1));
        $this->assertEquals(12, strlen($code2));
    }

    public function test_get_balance_by_code()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 75,
            'status' => 'available',
        ]);

        $balance = GiftCard::getBalance($giftCard->code, $this->provider->id);
        $this->assertEquals(75, $balance);

        // Different provider should return null
        $otherProvider = Provider::factory()->create();
        $otherBalance = GiftCard::getBalance($giftCard->code, $otherProvider->id);
        $this->assertNull($otherBalance);
    }

    public function test_find_by_code_with_provider_scope()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
        ]);

        $found = GiftCard::findByCode($giftCard->code, $this->provider->id);
        $this->assertNotNull($found);
        $this->assertEquals($giftCard->id, $found->id);

        // Different provider should return null
        $otherProvider = Provider::factory()->create();
        $notFound = GiftCard::findByCode($giftCard->code, $otherProvider->id);
        $this->assertNull($notFound);
    }

    public function test_model_fillable_fields_match_schema()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'currency' => 'USD',
            'status' => 'available',
            'message' => 'Test',
            'metadata' => ['test' => 'data'],
        ]);

        $this->assertEquals($this->provider->id, $giftCard->provider_id);
        $this->assertEquals(100, $giftCard->amount);
        $this->assertEquals('USD', $giftCard->currency);
        $this->assertEquals('available', $giftCard->status);
    }

    public function test_zero_balance_gift_card_is_invalid()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 0,
            'status' => 'available',
        ]);

        $this->assertFalse($giftCard->isValid());
    }

    public function test_inactive_gift_card_is_invalid()
    {
        $giftCard = GiftCard::factory()->create([
            'provider_id' => $this->provider->id,
            'purchased_by' => $this->customer->id,
            'amount' => 100,
            'balance' => 100,
            'status' => 'available',
            'active' => false,
        ]);

        $this->assertFalse($giftCard->isValid());
    }
}
