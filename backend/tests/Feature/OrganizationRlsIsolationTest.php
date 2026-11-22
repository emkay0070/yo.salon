<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OrganizationRlsIsolationTest
 *
 * Verifies that PostgreSQL Row-Level Security enforces Organization-level
 * tenant isolation on the `tybuel_app` runtime role (NOBYPASSRLS).
 *
 * All assertions execute strictly inside transactions using transaction-local
 * context (`set_config(..., true)`) on the `pgsql_tybuel` runtime connection.
 *
 * This test manages its own fixture lifecycle on the `pgsql_tybuel_admin`
 * superuser connection without touching the default `yo_salon` database.
 */
class OrganizationRlsIsolationTest extends TestCase
{
    /**
     * Runtime connection under test.
     * Uses role `tybuel_app` with NOBYPASSRLS and NO table ownership.
     */
    protected string $rlsConnection = 'pgsql_tybuel';

    /**
     * Admin connection for test fixture setup and teardown.
     * Uses role `postgres` (superuser).
     */
    protected string $adminConnection = 'pgsql_tybuel_admin';

    protected string $orgAId;
    protected string $orgBId;
    protected string $providerAId;
    protected string $providerBId;
    protected int $ownerAId;
    protected int $ownerBId;
    protected string $providerTypeId;

    /**
     * Set the PostgreSQL session variable inside an active transaction
     * on the tybuel_app runtime connection with is_local = true.
     */
    private function setRlsContext(string $orgId): void
    {
        DB::setDefaultConnection($this->rlsConnection);
        $conn = DB::connection($this->rlsConnection);

        if ($conn->transactionLevel() === 0) {
            $conn->beginTransaction();
        }

        $conn->statement(
            "SELECT set_config('app.current_organization_id', ?, true)",
            [$orgId]
        );
    }

    /**
     * Clear the PostgreSQL session variable and commit the transaction.
     */
    private function clearRlsContext(): void
    {
        $conn = DB::connection($this->rlsConnection);
        if ($conn->transactionLevel() > 0) {
            $conn->statement("SELECT set_config('app.current_organization_id', '', true)");
            $conn->commit();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Guard: skip tests gracefully if the tybuel runtime or admin connection is not reachable
        try {
            DB::connection($this->rlsConnection)->getPdo();
            DB::connection($this->adminConnection)->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'tybuel PostgreSQL connections unavailable. Error: ' . $e->getMessage()
            );
        }

        // Disable row security on the admin connection so fixtures can be seeded/cleaned freely
        DB::connection($this->adminConnection)->statement('SET row_security = off');

        // Fetch a valid provider_type_id
        $typeId = DB::connection($this->adminConnection)
            ->table('provider_types')
            ->value('id');

        if (!$typeId) {
            $this->markTestSkipped('No provider_types available in tybuel database.');
        }
        $this->providerTypeId = (string) $typeId;

        // Clean up any stale test fixtures before running
        $this->cleanupFixtures();

        // Seed Users with unique emails per run
        $this->ownerAId = DB::connection($this->adminConnection)
            ->table('users')
            ->insertGetId([
                'name'       => 'Test Owner Alpha',
                'email'      => 'owner-a-' . Str::random(8) . '@test.tybuel',
                'password'   => bcrypt('secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->ownerBId = DB::connection($this->adminConnection)
            ->table('users')
            ->insertGetId([
                'name'       => 'Test Owner Beta',
                'email'      => 'owner-b-' . Str::random(8) . '@test.tybuel',
                'password'   => bcrypt('secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // Seed Organizations
        $this->orgAId = Str::uuid()->toString();
        DB::connection($this->adminConnection)
            ->table('organizations')
            ->insert([
                'id'            => $this->orgAId,
                'name'          => 'Org Alpha Salon',
                'slug'          => 'org-alpha-' . Str::random(8),
                'type'          => 'business',
                'status'        => 'active',
                'owner_user_id' => $this->ownerAId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

        $this->orgBId = Str::uuid()->toString();
        DB::connection($this->adminConnection)
            ->table('organizations')
            ->insert([
                'id'            => $this->orgBId,
                'name'          => 'Org Beta Spa',
                'slug'          => 'org-beta-' . Str::random(8),
                'type'          => 'business',
                'status'        => 'active',
                'owner_user_id' => $this->ownerBId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

        // Seed Organization Memberships
        DB::connection($this->adminConnection)
            ->table('organization_memberships')
            ->insert([
                [
                    'id'              => Str::uuid()->toString(),
                    'organization_id' => $this->orgAId,
                    'user_id'         => $this->ownerAId,
                    'role'            => 'org_owner',
                    'status'          => 'active',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ],
                [
                    'id'              => Str::uuid()->toString(),
                    'organization_id' => $this->orgBId,
                    'user_id'         => $this->ownerBId,
                    'role'            => 'org_owner',
                    'status'          => 'active',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ],
            ]);

        // Seed Providers
        $this->providerAId = Str::uuid()->toString();
        DB::connection($this->adminConnection)
            ->table('providers')
            ->insert([
                'id'               => $this->providerAId,
                'organization_id'  => $this->orgAId,
                'provider_type_id' => $this->providerTypeId,
                'display_name'     => 'Provider Alpha',
                'status'           => 'active',
                'active'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

        $this->providerBId = Str::uuid()->toString();
        DB::connection($this->adminConnection)
            ->table('providers')
            ->insert([
                'id'               => $this->providerBId,
                'organization_id'  => $this->orgBId,
                'provider_type_id' => $this->providerTypeId,
                'display_name'     => 'Provider Beta',
                'status'           => 'active',
                'active'           => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
    }

    protected function tearDown(): void
    {
        try {
            $this->clearRlsContext();
        } catch (\Throwable $e) {
            // Ignore context cleanup failure if connection is closed
        }

        $this->cleanupFixtures();

        parent::tearDown();
    }

    private function cleanupFixtures(): void
    {
        try {
            $admin = DB::connection($this->adminConnection);
            $admin->statement('SET row_security = off');

            $admin->statement("
                DELETE FROM bookings WHERE provider_id IN (
                    SELECT id FROM providers WHERE organization_id IN (
                        SELECT id FROM organizations WHERE owner_user_id IN (
                            SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                        )
                    )
                )
            ");

            $admin->statement("
                DELETE FROM services WHERE provider_id IN (
                    SELECT id FROM providers WHERE organization_id IN (
                        SELECT id FROM organizations WHERE owner_user_id IN (
                            SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                        )
                    )
                )
            ");

            $admin->statement("
                DELETE FROM customer_providers WHERE provider_id IN (
                    SELECT id FROM providers WHERE organization_id IN (
                        SELECT id FROM organizations WHERE owner_user_id IN (
                            SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                        )
                    )
                )
            ");

            $admin->statement("
                DELETE FROM providers WHERE organization_id IN (
                    SELECT id FROM organizations WHERE owner_user_id IN (
                        SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                    )
                )
            ");

            $admin->statement("
                DELETE FROM organization_memberships WHERE organization_id IN (
                    SELECT id FROM organizations WHERE owner_user_id IN (
                        SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                    )
                ) OR user_id IN (
                    SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                )
            ");

            $admin->statement("
                DELETE FROM organizations WHERE owner_user_id IN (
                    SELECT id FROM users WHERE email LIKE '%@test.tybuel'
                )
            ");

            $admin->statement("
                DELETE FROM users WHERE email LIKE '%@test.tybuel'
            ");
        } catch (\Throwable $e) {
            // Ignore during setup/teardown if tables or connection are not available
        }
    }

    // =========================================================================
    // 1. CROSS-TENANT READ ISOLATION
    // =========================================================================

    #[Test]
    public function org_a_cannot_read_org_b_providers(): void
    {
        $this->setRlsContext($this->orgAId);

        $providerIds = DB::connection($this->rlsConnection)
            ->table('providers')
            ->pluck('id')
            ->all();

        $this->assertContains($this->providerAId, $providerIds, 'Org A should see its own provider');
        $this->assertNotContains($this->providerBId, $providerIds, 'Org A must NOT see Org B provider');
    }

    #[Test]
    public function org_b_cannot_read_org_a_providers(): void
    {
        $this->setRlsContext($this->orgBId);

        $providerIds = DB::connection($this->rlsConnection)
            ->table('providers')
            ->pluck('id')
            ->all();

        $this->assertContains($this->providerBId, $providerIds, 'Org B should see its own provider');
        $this->assertNotContains($this->providerAId, $providerIds, 'Org B must NOT see Org A provider');
    }

    #[Test]
    public function org_a_cannot_read_org_b_organization_row(): void
    {
        $this->setRlsContext($this->orgAId);

        $orgIds = DB::connection($this->rlsConnection)
            ->table('organizations')
            ->pluck('id')
            ->all();

        $this->assertContains($this->orgAId, $orgIds, 'Org A should see itself');
        $this->assertNotContains($this->orgBId, $orgIds, 'Org A must NOT see Org B');
    }

    #[Test]
    public function org_a_cannot_read_org_b_memberships(): void
    {
        $this->setRlsContext($this->orgAId);

        $membershipOrgIds = DB::connection($this->rlsConnection)
            ->table('organization_memberships')
            ->pluck('organization_id')
            ->unique()
            ->all();

        foreach ($membershipOrgIds as $oid) {
            $this->assertEquals($this->orgAId, $oid, 'Org A must only see its own memberships');
        }
    }

    // =========================================================================
    // 2. CROSS-TENANT WRITE / UPDATE / DELETE PROTECTION
    // =========================================================================

    #[Test]
    public function org_a_cannot_update_org_b_provider(): void
    {
        $this->setRlsContext($this->orgAId);

        $affected = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('id', $this->providerBId)
            ->update(['display_name' => 'Tampered By Org A']);

        $this->assertEquals(0, $affected, 'RLS must prevent updating rows outside current tenant');

        $this->clearRlsContext();

        // Verify Org B's provider remains unmodified
        $this->setRlsContext($this->orgBId);
        $providerBName = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('id', $this->providerBId)
            ->value('display_name');

        $this->assertEquals('Provider Beta', $providerBName);
    }

    #[Test]
    public function org_a_cannot_delete_org_b_provider(): void
    {
        $this->setRlsContext($this->orgAId);

        $deleted = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('id', $this->providerBId)
            ->delete();

        $this->assertEquals(0, $deleted, 'RLS must prevent deleting rows outside current tenant');

        $this->clearRlsContext();

        // Verify Org B's provider still exists
        $this->setRlsContext($this->orgBId);
        $exists = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('id', $this->providerBId)
            ->exists();

        $this->assertTrue($exists, 'Org B provider must not be deleted');
    }

    #[Test]
    public function org_a_cannot_insert_provider_for_org_b(): void
    {
        $this->setRlsContext($this->orgAId);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // WITH CHECK policy on providers enforces organization_id = current_org_id.
        // Attempting to insert a provider with Org B's ID while in Org A context must throw.
        DB::connection($this->rlsConnection)->table('providers')->insert([
            'id'               => Str::uuid()->toString(),
            'organization_id'  => $this->orgBId,
            'provider_type_id' => $this->providerTypeId,
            'display_name'     => 'Malicious Provider In Org B',
            'status'           => 'active',
            'active'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // =========================================================================
    // 3. OWN-TENANT ACCESS
    // =========================================================================

    #[Test]
    public function org_a_can_read_and_modify_its_own_provider(): void
    {
        $this->setRlsContext($this->orgAId);

        $affected = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('id', $this->providerAId)
            ->update(['display_name' => 'Provider Alpha Updated']);

        $this->assertEquals(1, $affected, 'Org A should be able to update its own provider');

        $updatedName = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('id', $this->providerAId)
            ->value('display_name');

        $this->assertEquals('Provider Alpha Updated', $updatedName);
    }

    // =========================================================================
    // 4. MULTI-PROVIDER ORGANIZATION
    // =========================================================================

    #[Test]
    public function multi_provider_organization_sees_all_its_providers(): void
    {
        $providerA2Id = Str::uuid()->toString();
        DB::connection($this->adminConnection)->table('providers')->insert([
            'id'               => $providerA2Id,
            'organization_id'  => $this->orgAId,
            'provider_type_id' => $this->providerTypeId,
            'display_name'     => 'Provider Alpha Second Branch',
            'status'           => 'active',
            'active'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->setRlsContext($this->orgAId);

        $providerIds = DB::connection($this->rlsConnection)
            ->table('providers')
            ->pluck('id')
            ->all();

        $this->assertContains($this->providerAId, $providerIds, 'First provider must be visible');
        $this->assertContains($providerA2Id, $providerIds, 'Second provider must be visible');
        $this->assertNotContains($this->providerBId, $providerIds, 'Org B provider must NOT be visible');
    }

    // =========================================================================
    // 5. EMPTY / MISSING CONTEXT (FAIL CLOSED)
    // =========================================================================

    #[Test]
    public function empty_context_does_not_expose_organization_rows(): void
    {
        $this->setRlsContext('');

        $orgs = DB::connection($this->rlsConnection)
            ->table('organizations')
            ->get();

        $this->assertCount(0, $orgs, 'Empty RLS context must not expose any organization rows');
    }

    #[Test]
    public function empty_context_does_not_expose_existing_bookings(): void
    {
        $admin = DB::connection($this->adminConnection);

        // Seed a service and booking under Provider A
        $serviceId = Str::uuid()->toString();
        $admin->table('services')->insert([
            'id'          => $serviceId,
            'provider_id' => $this->providerAId,
            'name'        => 'Haircut Seed',
            'price'       => 25000,
            'duration'    => 30,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $bookingId = Str::uuid()->toString();
        $admin->table('bookings')->insert([
            'id'          => $bookingId,
            'provider_id' => $this->providerAId,
            'service_id'  => $serviceId,
            'date'        => now()->toDateString(),
            'time'        => '10:00',
            'status'      => 'confirmed',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->setRlsContext('');

        $bookings = DB::connection($this->rlsConnection)
            ->table('bookings')
            ->get();

        $this->assertCount(0, $bookings, 'Empty RLS context must never expose existing bookings');
    }

    // =========================================================================
    // 6. PUBLIC DISCOVERY & GUEST BOOKING
    // =========================================================================

    #[Test]
    public function public_context_allows_discovery_of_active_providers_and_services(): void
    {
        $serviceId = Str::uuid()->toString();
        DB::connection($this->adminConnection)->table('services')->insert([
            'id'          => $serviceId,
            'provider_id' => $this->providerAId,
            'name'        => 'Standard Haircut',
            'price'       => 20000,
            'duration'    => 45,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->setRlsContext('');

        // 1. Active provider discovery
        $activeProviders = DB::connection($this->rlsConnection)
            ->table('providers')
            ->where('active', true)
            ->pluck('id')
            ->all();

        $this->assertContains($this->providerAId, $activeProviders);

        // 2. Active service discovery
        $activeServices = DB::connection($this->rlsConnection)
            ->table('services')
            ->where('active', true)
            ->pluck('id')
            ->all();

        $this->assertContains($serviceId, $activeServices);
    }

    #[Test]
    public function public_context_does_not_expose_inactive_services(): void
    {
        $inactiveServiceId = Str::uuid()->toString();
        DB::connection($this->adminConnection)->table('services')->insert([
            'id'          => $inactiveServiceId,
            'provider_id' => $this->providerAId,
            'name'        => 'Hidden Internal Service',
            'price'       => 0,
            'duration'    => 30,
            'active'      => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->setRlsContext('');

        $visibleServices = DB::connection($this->rlsConnection)
            ->table('services')
            ->pluck('id')
            ->all();

        $this->assertNotContains($inactiveServiceId, $visibleServices, 'Public context must NOT show inactive services');
    }

    #[Test]
    public function public_context_allows_guest_booking_insert_for_active_provider(): void
    {
        $serviceId = Str::uuid()->toString();
        DB::connection($this->adminConnection)->table('services')->insert([
            'id'          => $serviceId,
            'provider_id' => $this->providerAId,
            'name'        => 'Guest Booking Service',
            'price'       => 15000,
            'duration'    => 30,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->setRlsContext('');

        $guestBookingId = Str::uuid()->toString();

        // Guest booking policy allows INSERT when provider is active and context is empty
        DB::connection($this->rlsConnection)->table('bookings')->insert([
            'id'          => $guestBookingId,
            'provider_id' => $this->providerAId,
            'service_id'  => $serviceId,
            'date'        => now()->toDateString(),
            'time'        => '11:00',
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // But guest CANNOT read it back with empty context (SELECT policy is tenant-only)
        $readByGuest = DB::connection($this->rlsConnection)
            ->table('bookings')
            ->where('id', $guestBookingId)
            ->exists();

        $this->assertFalse($readByGuest, 'Guest should NOT be able to SELECT bookings even after insertion');

        $this->clearRlsContext();

        // But the organization CAN read it once context is established
        $this->setRlsContext($this->orgAId);
        $readByOrg = DB::connection($this->rlsConnection)
            ->table('bookings')
            ->where('id', $guestBookingId)
            ->exists();

        $this->assertTrue($readByOrg, 'Organization must be able to see the guest booking');
    }

    // =========================================================================
    // 7. CONNECTION REUSE & LEAK PREVENTION
    // =========================================================================

    #[Test]
    public function clearing_context_prevents_leakage_to_subsequent_queries_on_same_connection(): void
    {
        // 1. Establish Org A context inside transaction
        $this->setRlsContext($this->orgAId);
        $countInA = DB::connection($this->rlsConnection)->table('providers')->count();
        $this->assertGreaterThanOrEqual(1, $countInA);

        // 2. Commit and clear context (transaction ends, is_local reverts)
        $this->clearRlsContext();

        // 3. New transaction without context sees 0 organization rows
        $this->setRlsContext('');
        $orgCount = DB::connection($this->rlsConnection)->table('organizations')->count();
        $this->assertEquals(0, $orgCount, 'No organization rows should be visible after clearing context');
    }
}
