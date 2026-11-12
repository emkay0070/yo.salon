<?php

namespace Tests\Feature;

use App\Models\GiftCard;
use App\Models\GiftCardPurchaseIntent;
use App\Models\Provider;
use App\Models\Customer;
use App\Models\Notification;
use App\Domain\Finance\Ledger\LedgerAccount;
use App\Domain\Finance\Ledger\LedgerEntry;
use App\Services\GiftCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftCardPaymentFlowTest extends TestCase
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
        $this->giftCardService = app(GiftCardService::class);
    }

    public function test_gift_card_purchase_creates_intent_and_processes_payment_idempotently()
    {
        // 1. Initialize Purchase
        $intent = $this->giftCardService->purchaseGiftCard(
            $this->customer->id,
            $this->provider->id,
            100.00,
            'Happy Birthday!'
        );

        $this->assertInstanceOf(GiftCardPurchaseIntent::class, $intent);
        $this->assertEquals('pending', $intent->status);
        $this->assertEquals(100.00, $intent->amount);

        // 2. Confirm Payment
        $this->giftCardService->confirmPayment($intent->id, 'mobile_money', 'FLW-12345');

        // Verify Intent was marked paid and a gift card was issued
        $intent->refresh();
        $this->assertEquals('paid', $intent->status);
        $this->assertEquals('mobile_money', $intent->payment_method);
        $this->assertEquals('FLW-12345', $intent->provider_reference);
        $this->assertNotNull($intent->gift_card_id);

        // Verify Gift Card
        $giftCard = GiftCard::find($intent->gift_card_id);
        $this->assertEquals(100.00, $giftCard->amount);
        $this->assertEquals('available', $giftCard->status);

        // Verify Finance Journal (Deferred Revenue)
        $assetAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $this->provider->id)
            ->where('account_type', 'mobile_money')
            ->first();
            
        $payableAccount = LedgerAccount::where('owner_type', Provider::class)
            ->where('owner_id', $this->provider->id)
            ->where('account_type', 'payable')
            ->first();

        $this->assertNotNull($assetAccount, 'Asset account should be created');
        $this->assertNotNull($payableAccount, 'Payable account should be created');

        // Normal balance of mobile_money is debit (+), payable is credit (+)
        // However, JournalEntry implementation treats credit as increment and debit as decrement.
        // Therefore, 100 debit to mobile_money => stored balance -100
        // 100 credit to payable => stored balance +100
        $this->assertEquals(-100.00, $assetAccount->balance);
        $this->assertEquals(100.00, $payableAccount->balance);

        // Verify Notifications (1 for Customer, 1 for Provider)
        $customerNotifications = Notification::where('customer_id', $this->customer->id)->get();
        $providerNotifications = Notification::where('provider_id', $this->provider->id)->get();
        
        $this->assertCount(1, $customerNotifications, 'Exactly 1 Customer notification should be created');
        $this->assertCount(1, $providerNotifications, 'Exactly 1 Provider notification should be created');
        
        $customerNotif = $customerNotifications->first();
        $this->assertEquals('gift_card_payment_confirmed', $customerNotif->type);
        $this->assertEquals('gift', $customerNotif->icon);
        $this->assertStringContainsString('100', $customerNotif->message);
        $this->assertEquals('/portal/gift-cards', $customerNotif->action_url);

        $providerNotif = $providerNotifications->first();
        $this->assertEquals('gift_card_payment_confirmed', $providerNotif->type);
        $this->assertEquals('gift', $providerNotif->icon);
        $this->assertStringContainsString('100', $providerNotif->message);
        $this->assertEquals('/dashboard/gift-cards', $providerNotif->action_url);

        // 3. Test Idempotency (repeated callback)
        // Ensure no new gift cards or journals are created.
        $initialGCs = GiftCard::count();
        $initialEntries = LedgerEntry::count();
        $initialNotifications = Notification::count();

        $this->giftCardService->confirmPayment($intent->id, 'mobile_money', 'FLW-12345');

        $this->assertEquals($initialGCs, GiftCard::count());
        $this->assertEquals($initialEntries, LedgerEntry::count());
        $this->assertEquals($initialNotifications, Notification::count(), 'No duplicate notifications should be created');
    }

    public function test_gift_card_payment_failure_marks_intent_failed()
    {
        $intent = $this->giftCardService->purchaseGiftCard(
            $this->customer->id,
            $this->provider->id,
            50.00
        );

        $this->giftCardService->failPayment($intent->id, 'card', 'FLW-FAILED');

        $intent->refresh();
        $this->assertEquals('failed', $intent->status);
        $this->assertNull($intent->gift_card_id);

        // Verify Notifications (1 for Customer, 0 for Provider)
        $customerNotifications = Notification::where('customer_id', $this->customer->id)->get();
        $providerNotifications = Notification::where('provider_id', $this->provider->id)->get();
        
        $this->assertCount(1, $customerNotifications, 'Exactly 1 Customer notification should be created on failure');
        $this->assertCount(0, $providerNotifications, 'No Provider notification should be created on failure');
        
        $customerNotif = $customerNotifications->first();
        $this->assertEquals('gift_card_payment_failed', $customerNotif->type);
        $this->assertEquals('alert-triangle', $customerNotif->icon);
        $this->assertStringContainsString('50', $customerNotif->message);
        $this->assertEquals('/portal/gift-cards', $customerNotif->action_url);
    }
}
