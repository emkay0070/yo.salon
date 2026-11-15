<?php

namespace Tests\Feature\Finance\Payout;

use App\Domain\Finance\Payout\Payout;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\PaymentProfile;
use App\Models\Provider;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_a_manual_payout_for_a_settlement(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $provider = Provider::factory()->create();
        $specialist = Specialist::factory()->create();

        DB::table('provider_users')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'role' => 'manager',
            'authority_scope' => 'provider_wide',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PaymentProfile::create([
            'owner_type' => Specialist::class,
            'owner_id' => $specialist->id,
            'method' => 'cash',
            'is_default' => true,
        ]);

        $settlement = Settlement::create([
            'provider_id' => $provider->id,
            'payable_type' => Provider::class,
            'payable_id' => $provider->id,
            'recipient_type' => Specialist::class,
            'recipient_id' => $specialist->id,
            'amount' => 50000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/providers/{$provider->slug}/compensation/settlements/{$settlement->id}/payout",
            [
                'amount' => 50000,
                'method' => 'cash',
                'execution_mode' => 'manual',
            ]
        );

        $response->assertStatus(201);

        $this->assertDatabaseHas('payouts', [
            'settlement_id' => $settlement->id,
            'recipient_type' => Specialist::class,
            'recipient_id' => $specialist->id,
            'execution_mode' => 'manual',
            'status' => Payout::STATUS_COMPLETED,
        ]);

        $this->assertEquals(Settlement::STATUS_PAID, $settlement->fresh()->status);
    }
}
