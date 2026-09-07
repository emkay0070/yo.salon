<?php

namespace Tests\Feature\Finance\Payout;

use Tests\TestCase;
use App\Models\Salon;
use App\Models\User;
use App\Models\Specialist;
use App\Models\PaymentProfile;
use App\Models\PaymentMethod;
use App\Domain\Finance\Settlement\Settlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PayoutReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create(['status' => 'active']);
        $this->salon     = Salon::factory()->create();
        $this->specialist = Specialist::factory()->create();

        // Attach the user as a manager via the salon_users pivot
        DB::table('salon_users')->insert([
            'user_id'    => $this->user->id,
            'salon_id'   => $this->salon->id,
            'role'       => 'manager',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Settlement: salon (payable) owes specialist (recipient)
        $this->settlement = Settlement::create([
            'payable_type'   => Salon::class,
            'payable_id'     => $this->salon->id,
            'recipient_type' => Specialist::class,
            'recipient_id'   => $this->specialist->id,
            'amount'         => 50000,
            'currency'       => 'UGX',
            'status'         => 'pending',
        ]);
    }

    public function test_readiness_is_manual_only_if_no_profile_exists()
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson(
            "/api/v1/salons/{$this->salon->slug}/compensation/settlements/{$this->settlement->id}/payout-readiness"
        );

        $response->assertStatus(200)
                 ->assertJson([
                     'automated_available' => false,
                     'manual_available'    => true,
                     'reason'              => 'Recipient has not added a payment destination.',
                 ]);
    }

    public function test_readiness_is_manual_only_if_method_is_cash()
    {
        PaymentProfile::create([
            'owner_type' => Specialist::class,
            'owner_id'   => $this->specialist->id,
            'method'     => 'cash',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson(
            "/api/v1/salons/{$this->salon->slug}/compensation/settlements/{$this->settlement->id}/payout-readiness"
        );

        $response->assertStatus(200)
                 ->assertJson([
                     'automated_available' => false,
                     'manual_available'    => true,
                     'reason'              => 'Cash payouts are always recorded manually.',
                 ]);
    }

    public function test_readiness_is_automated_if_profile_and_disbursement_config_exist()
    {
        PaymentProfile::create([
            'owner_type'   => Specialist::class,
            'owner_id'     => $this->specialist->id,
            'method'       => 'mtn',
            'phone_number' => '0771234567',
            'is_default'   => true,
        ]);

        PaymentMethod::factory()->create([
            'salon_id'             => $this->salon->id,
            'provider'             => 'mtn_disbursement',
            'is_active'            => true,
            'api_subscription_key' => 'test',
            'api_key'              => 'test',
            'merchant_id'          => 'test',
            'environment'          => 'sandbox',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson(
            "/api/v1/salons/{$this->salon->slug}/compensation/settlements/{$this->settlement->id}/payout-readiness"
        );

        $response->assertStatus(200)
                 ->assertJson([
                     'automated_available' => true,
                     'manual_available'    => true,
                     'method'              => 'mtn',
                 ]);
    }

    public function test_manager_gets_masked_destination_on_readiness_response()
    {
        PaymentProfile::create([
            'owner_type'   => Specialist::class,
            'owner_id'     => $this->specialist->id,
            'method'       => 'mtn',
            'phone_number' => '0771234567',
            'is_default'   => true,
        ]);

        // No disbursement config: automated_available=false, but destination should be masked
        $response = $this->actingAs($this->user, 'sanctum')->getJson(
            "/api/v1/salons/{$this->salon->slug}/compensation/settlements/{$this->settlement->id}/payout-readiness"
        );

        $response->assertStatus(200)
                 ->assertJson([
                     'automated_available' => false,
                     'destination'         => '••••4567',
                 ]);
    }
}
