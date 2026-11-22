<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Models\ProviderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2: public Provider API payloads derive classification from the
 * provider_type_id relationship while preserving the legacy `type` key.
 */
class ProviderApiTypeSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_show_exposes_canonical_type_and_provider_type(): void
    {
        $provider = Provider::factory()->ofType('spa')->create();

        $response = $this->getJson('/api/v1/providers/' . $provider->slug);

        $response->assertStatus(200);
        $this->assertSame('spa', $response->json('type'));
        $this->assertSame('spa', $response->json('provider_type'));
    }

    public function test_provider_index_filters_through_provider_type_id(): void
    {
        $spa = Provider::factory()->ofType('spa')->create();
        Provider::factory()->ofType('salon')->create();

        // /salons is the public alias consumed by the booking frontend.
        $response = $this->getJson('/api/v1/salons?type=spa');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($spa->id, $ids);
        $this->assertCount(1, $ids);
        $this->assertSame('spa', $response->json('data.0.provider_type'));
        $this->assertSame('spa', $response->json('data.0.type'));
    }

    public function test_provider_show_derives_type_when_legacy_column_is_dropped(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('providers', 'type'),
            'Phase 5 verification: providers.type column must be dropped from the database.'
        );

        $provider = Provider::factory()->ofType('spa')->create();

        $response = $this->getJson('/api/v1/providers/' . $provider->slug);

        $response->assertStatus(200);
        $this->assertSame('spa', $response->json('type'));
        $this->assertSame('spa', $response->json('provider_type'));
    }

    public function test_provider_show_returns_null_type_when_relation_is_missing(): void
    {
        // A missing relation cannot normally persist because of the FK, so
        // detach the record inside the test transaction to exercise the
        // explicit, non-fallback serialization path.
        $provider = Provider::factory()->ofType('salon')->create();

        DB::statement('ALTER TABLE providers DROP CONSTRAINT IF EXISTS providers_provider_type_id_foreign');
        DB::statement('ALTER TABLE providers ALTER COLUMN provider_type_id DROP NOT NULL');
        DB::table('providers')
            ->where('id', $provider->id)
            ->update(['provider_type_id' => null]);

        $response = $this->getJson('/api/v1/providers/' . $provider->slug);

        $response->assertStatus(200);
        $this->assertNull($response->json('type'));
        $this->assertNull($response->json('provider_type'));
        $this->assertNotSame('salon', $response->json('provider_type'));
    }
}
