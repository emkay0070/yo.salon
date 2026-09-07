<?php

namespace Tests\Feature\Finance\Payout;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Salon;
use App\Models\Specialist;
use App\Models\PaymentProfile;
use App\Models\PaymentMethod;
use App\Domain\Finance\Payout\Payout;
use App\Domain\Finance\Settlement\Settlement;
use App\Domain\Finance\Payout\Execution\AutomatedExecution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DisbursementExecutionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = Salon::factory()->create();
        $this->specialist = Specialist::factory()->create();

        // Setup Salon Disbursement Credentials (MTN).
        // PaymentMethod is queried by salon_id + provider = 'mtn_disbursement'.
        $this->disbursementConfig = PaymentMethod::factory()->create([
            'salon_id'             => $this->salon->id,
            'provider'             => 'mtn_disbursement',
            'type'                 => 'mobile_money',
            'display_name'         => 'MTN Disbursement',
            'api_subscription_key' => 'test_sub_key',
            'api_key'              => 'test_api_key',
            'merchant_id'          => 'test_user',
            'environment'          => 'sandbox',
            'is_active'            => true,
        ]);

        // Setup Specialist Payment Profile (MTN outbound destination).
        $this->paymentProfile = PaymentProfile::create([
            'owner_type'   => Specialist::class,
            'owner_id'     => $this->specialist->id,
            'method'       => 'mtn',
            'phone_number' => '256770000000',
            'is_default'   => true,
        ]);

        // Settlement: the Salon (payable) owes the Specialist (recipient).
        $this->settlement = Settlement::create([
            'payable_type'   => \App\Models\Salon::class,
            'payable_id'     => $this->salon->id,
            'recipient_type' => Specialist::class,
            'recipient_id'   => $this->specialist->id,
            'amount'         => 150000,
            'currency'       => 'UGX',
            'status'         => 'pending',
        ]);

        // Pending automated Payout for this settlement.
        $this->payout = Payout::create([
            'settlement_id'  => $this->settlement->id,
            'recipient_type' => Specialist::class,
            'recipient_id'   => $this->specialist->id,
            'amount'         => 150000,
            'currency'       => 'UGX',
            'method'         => 'mtn',
            'execution_mode' => 'automated',
            'status'         => 'pending',
        ]);
    }

    public function test_automated_execution_dispatches_transfer_and_updates_status()
    {
        // Fake MTN API calls
        Http::fake([
            'sandbox.momodeveloper.mtn.com/disbursement/token/' => Http::response(['access_token' => 'mock_token'], 200),
            'sandbox.momodeveloper.mtn.com/disbursement/v1_0/transfer' => Http::response([], 202),
        ]);

        $execution = new AutomatedExecution();
        
        $this->assertTrue($execution->supports('mtn'));

        // Execute payout
        $execution->execute($this->payout);

        // Assert payout was updated
        $this->payout->refresh();
        $this->assertEquals('processing', $this->payout->status);
        $this->assertNotNull($this->payout->reference); // Internal reference generated
        $this->assertStringStartsWith('OUT-', $this->payout->reference);
        $this->assertEquals('mtn', $this->payout->provider);
    }

    public function test_disbursement_webhook_completes_payout()
    {
        $this->payout->update([
            'reference' => 'OUT-12345678',
            'status' => 'processing',
        ]);

        $payload = [
            'externalId' => 'OUT-12345678',
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'MTN-TXN-999',
        ];

        $response = $this->postJson('/api/v1/webhooks/disbursement/mtn', $payload, [
            'X-Signature' => 'mock_signature'
        ]);

        $response->assertStatus(200);

        $this->payout->refresh();
        $this->assertEquals('completed', $this->payout->status);
    }
    
    public function test_disbursement_webhook_fails_payout()
    {
        $this->payout->update([
            'reference' => 'OUT-12345678',
            'status' => 'processing',
        ]);

        $payload = [
            'externalId' => 'OUT-12345678',
            'status' => 'FAILED',
        ];

        $response = $this->postJson('/api/v1/webhooks/disbursement/mtn', $payload, [
            'X-Signature' => 'mock_signature'
        ]);

        $response->assertStatus(200);

        $this->payout->refresh();
        $this->assertEquals('failed', $this->payout->status);
    }
}
