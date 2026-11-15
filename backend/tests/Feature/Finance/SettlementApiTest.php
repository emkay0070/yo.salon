<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Compensation\CompensationPolicy;
use App\Domain\Finance\Compensation\Earning;
use App\Domain\Finance\Settlement\Settlement;
use App\Models\Provider;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Provider $provider;
    private Specialist $specialist;
    private Settlement $settlement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 'active']);
        $this->provider = Provider::factory()->create();
        $this->specialist = Specialist::factory()->create();

        DB::table('provider_users')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'provider_id' => $this->provider->id,
            'role' => 'owner',
            'authority_scope' => 'provider_wide',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->settlement = Settlement::create([
            'provider_id' => $this->provider->id,
            'payable_type' => Provider::class,
            'payable_id' => $this->provider->id,
            'recipient_type' => Specialist::class,
            'recipient_id' => $this->specialist->id,
            'amount' => 25000,
            'currency' => 'UGX',
            'status' => Settlement::STATUS_PENDING,
        ]);
    }

    public function test_settlement_index_loads_supported_relations(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/settlements');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->settlement->id)
            ->assertJsonPath('0.recipient.id', $this->specialist->id);
    }

    public function test_settlement_show_includes_earnings(): void
    {
        $policy = CompensationPolicy::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'provider_type' => Provider::class,
            'provider_id' => $this->provider->id,
            'type' => 'commission',
            'rules' => [['service_category' => '*', 'rate' => 0.40]],
            'settlement_frequency' => 'monthly',
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        Earning::create([
            'compensatable_type' => Specialist::class,
            'compensatable_id' => $this->specialist->id,
            'source_type' => 'App\Models\Booking',
            'source_id' => (string) Str::uuid(),
            'compensation_policy_id' => $policy->id,
            'settlement_id' => $this->settlement->id,
            'gross_amount' => 25000,
            'status' => Earning::STATUS_PAYABLE,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/settlements/{$this->settlement->id}");

        $response->assertOk()
            ->assertJsonPath('id', $this->settlement->id)
            ->assertJsonCount(1, 'earnings');
    }
}
