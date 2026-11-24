<?php

namespace Tests\Feature;

use App\Http\Middleware\SetOrganizationDatabaseContext;
use App\Models\BrandExperience;
use App\Models\Provider;
use App\Models\ProviderType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OrganizationDbContextMiddlewareTest
 *
 * Focused verification of the SetOrganizationDatabaseContext transaction
 * lifecycle against a real PostgreSQL connection (`pgsql_tybuel`):
 *
 *   1. Successful tenant-context initialization, commit and cleanup.
 *   2. Rollback after a PostgreSQL statement failure (aborted transaction).
 *   3. Subsequent requests on the same connection after a prior failure.
 *   4. The middleware never rolls back a transaction owned by another component.
 *
 * Also covers a BrandExperience endpoint regression for the provider_id fix.
 *
 * The `pgsql_tybuel` connection is used because the middleware only begins its
 * own transaction when transactionLevel() === 0. RefreshDatabase wraps the
 * default `pgsql` connection in a transaction but leaves `pgsql_tybuel` at level
 * 0, allowing the middleware to own (and roll back) its transaction exactly as
 * it does in production.
 */
class OrganizationDbContextMiddlewareTest extends TestCase
{
    protected string $connectionName = 'pgsql_tybuel';
    protected string $orgId;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection($this->connectionName)->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('pgsql_tybuel connection unavailable: ' . $e->getMessage());
        }

        $this->orgId = (string) Str::uuid();
    }

    protected function tearDown(): void
    {
        try {
            $conn = DB::connection($this->connectionName);
            while ($conn->transactionLevel() > 0) {
                $conn->rollBack();
            }
            $conn->statement("SELECT set_config('app.current_organization_id', '', false)");
        } catch (\Throwable $e) {
            // Ignore cleanup failure if connection is unavailable
        }

        DB::setDefaultConnection('pgsql');

        parent::tearDown();
    }

    private function makeRequest(string $orgId): Request
    {
        $request = Request::create('/api/v1/organization', 'GET');
        $request->attributes->set('organization_id', $orgId);

        return $request;
    }

    private function middleware(): SetOrganizationDatabaseContext
    {
        return new SetOrganizationDatabaseContext();
    }

    private function readContext(): ?string
    {
        $row = DB::connection($this->connectionName)
            ->selectOne("SELECT current_setting('app.current_organization_id', true) AS org_id");

        return $row ? (string) $row->org_id : null;
    }

    // =========================================================================
    // 1. SUCCESSFUL INITIALIZATION, COMMIT AND CLEANUP
    // =========================================================================

    #[Test]
    public function successful_request_sets_context_commits_and_restores_default_connection(): void
    {
        DB::setDefaultConnection($this->connectionName);
        $defaultBefore = DB::getDefaultConnection();

        $observedInside = null;

        $response = $this->middleware()->handle(
            $this->makeRequest($this->orgId),
            function ($req) use (&$observedInside) {
                $observedInside = $this->readContext();

                return response()->json(['ok' => true]);
            }
        );

        // Context was set for the duration of the request
        $this->assertEquals($this->orgId, $observedInside, 'RLS context must be set inside the transaction');

        // Transaction was committed (level 0), context discarded, default restored
        $conn = DB::connection($this->connectionName);
        $this->assertEquals(0, $conn->transactionLevel(), 'Transaction must be committed after a successful request');
        $this->assertSame('', $this->readContext(), 'RLS context must be cleared after a successful request');
        $this->assertEquals($defaultBefore, DB::getDefaultConnection(), 'Default connection must be restored');
        $this->assertNotNull($response);
    }

    // =========================================================================
    // 2. ROLLBACK AFTER POSTGRESQL STATEMENT FAILURE
    // =========================================================================

    #[Test]
    public function statement_failure_rolls_back_transaction_and_discards_context(): void
    {
        DB::setDefaultConnection($this->connectionName);
        $defaultBefore = DB::getDefaultConnection();

        $caught = null;

        try {
            $this->middleware()->handle(
                $this->makeRequest($this->orgId),
                function ($req) {
                    // Cause a PostgreSQL statement failure inside the active transaction.
                    // This aborts the transaction at the PostgreSQL level.
                    DB::connection($this->connectionName)
                        ->statement('SELECT this_function_does_not_exist()');

                    return response()->json(['ok' => true]);
                }
            );
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'The statement failure must propagate out of the middleware');

        // The middleware-owned transaction must be rolled back, not left open/aborted.
        $conn = DB::connection($this->connectionName);
        $this->assertEquals(0, $conn->transactionLevel(), 'Transaction must be rolled back after a statement failure');
        $this->assertSame('', $this->readContext(), 'RLS context must be discarded after rollback');
        $this->assertEquals($defaultBefore, DB::getDefaultConnection(), 'Default connection must be restored');
    }

    #[Test]
    public function middleware_does_not_set_config_inside_aborted_transaction(): void
    {
        DB::setDefaultConnection($this->connectionName);

        // A statement failure aborts the transaction. The error path must NOT
        // attempt set_config (which would raise "cannot execute SET in a failed
        // transaction"); it must roll back instead. We assert that the rollback
        // path completes without a secondary "failed transaction" error.
        $caught = null;
        $secondaryError = null;

        try {
            $this->middleware()->handle(
                $this->makeRequest($this->orgId),
                function ($req) {
                    DB::connection($this->connectionName)
                        ->statement('SELECT this_function_does_not_exist()');

                    return response()->json(['ok' => true]);
                }
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);

        // Ensure no secondary error (e.g. "cannot execute SET in a failed transaction")
        // masked the original failure.
        $this->assertStringNotContainsString(
            'failed transaction',
            $caught->getMessage(),
            'Rollback must not attempt set_config inside an aborted transaction'
        );

        // Connection must be usable again (transaction fully cleared).
        $conn = DB::connection($this->connectionName);
        $this->assertEquals(0, $conn->transactionLevel());
        $conn->statement("SELECT set_config('app.current_organization_id', '', true)");
    }

    // =========================================================================
    // 3. SUBSEQUENT REQUESTS ON THE SAME CONNECTION
    // =========================================================================

    #[Test]
    public function subsequent_request_on_same_connection_succeeds_after_prior_failure(): void
    {
        DB::setDefaultConnection($this->connectionName);

        // First request fails with a statement failure.
        try {
            $this->middleware()->handle(
                $this->makeRequest($this->orgId),
                function ($req) {
                    DB::connection($this->connectionName)
                        ->statement('SELECT this_function_does_not_exist()');

                    return response()->json(['ok' => true]);
                }
            );
            $this->fail('First request should have thrown');
        } catch (\Throwable $e) {
            // expected
        }

        // Second request on the SAME connection must succeed and establish context.
        $observedInside = null;
        $response = $this->middleware()->handle(
            $this->makeRequest($this->orgId),
            function ($req) use (&$observedInside) {
                $observedInside = $this->readContext();

                return response()->json(['ok' => true]);
            }
        );

        $this->assertNotNull($response);
        $this->assertEquals($this->orgId, $observedInside, 'Subsequent request must establish RLS context');

        $conn = DB::connection($this->connectionName);
        $this->assertEquals(0, $conn->transactionLevel(), 'Subsequent request transaction must be committed');
        $this->assertSame('', $this->readContext(), 'RLS context must be cleared after the subsequent request');
    }

    // =========================================================================
    // 4. DO NOT ROLL BACK A TRANSACTION OWNED BY ANOTHER COMPONENT
    // =========================================================================

    #[Test]
    public function middleware_does_not_roll_back_transaction_owned_by_another_component(): void
    {
        DB::setDefaultConnection($this->connectionName);
        $conn = DB::connection($this->connectionName);

        // Simulate another component (e.g. RefreshDatabase) already owning a transaction.
        $conn->beginTransaction();

        $caught = null;
        try {
            $this->middleware()->handle(
                $this->makeRequest($this->orgId),
                function ($req) {
                    throw new \RuntimeException('downstream failure');
                }
            );
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'The downstream exception must propagate');

        // The other component's transaction must remain OPEN (level 1) — the
        // middleware must not roll back a transaction it did not start.
        $this->assertEquals(1, $conn->transactionLevel(), 'Middleware must not roll back a transaction it does not own');

        // Clean up the transaction we opened manually.
        $conn->rollBack();
    }

    // =========================================================================
    // 5. BRANDEXPERIENCE ENDPOINT REGRESSION (provider_id fix)
    // =========================================================================

    #[Test]
    public function brand_experience_model_uses_provider_id_relationship(): void
    {
        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );

        $provider = Provider::factory()->create([
            'provider_type_id' => ProviderType::byKey('salon')->first()->id,
        ]);

        $brand = BrandExperience::create([
            'provider_id' => $provider->id,
            'experience_family' => 'luxury_noir',
            'primary_color' => '#FF622B',
            'secondary_color' => '#FF8C5A',
            'accent_color' => '#FFD700',
            'font_heading' => 'sora',
            'font_body' => 'inter',
            'white_label_enabled' => false,
        ]);

        // The relationship must resolve via provider_id (not legacy salon_id).
        $this->assertInstanceOf(Provider::class, $brand->provider);
        $this->assertEquals($provider->id, $brand->provider->id);

        // The inverse relationship must also resolve.
        $this->assertNotNull($provider->brandExperience);
        $this->assertEquals($brand->id, $provider->brandExperience->id);
    }

    #[Test]
    public function brand_experience_public_endpoint_resolves_by_slug(): void
    {
        ProviderType::firstOrCreate(
            ['key' => 'salon'],
            ['name' => 'Salon', 'is_active' => true, 'is_independent' => false, 'is_owner_managed' => false]
        );

        $provider = Provider::factory()->create([
            'provider_type_id' => ProviderType::byKey('salon')->first()->id,
        ]);

        $response = $this->getJson('/api/v1/salons/' . $provider->slug . '/brand-experience');

        $response->assertStatus(200)
            ->assertJsonPath('provider.id', $provider->id)
            ->assertJsonPath('provider.slug', $provider->slug);
    }
}
