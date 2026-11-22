<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Organization;
use App\Models\Provider;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationPrimaryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A separate connection holds the Provider row lock while the service tries
     * to create the first Location on another connection. The service must block
     * (and time out here) without creating a Location, proving that the Provider
     * row lock is the serialization mechanism for first-Location selection.
     */
    public function test_provider_row_lock_serializes_concurrent_first_location_creation()
    {
        $holderName = 'pgsql_location_lock_holder';
        $workerName = 'pgsql_location_lock_worker';

        foreach ([$holderName, $workerName] as $connectionName) {
            config([
                "database.connections.{$connectionName}" => config('database.connections.pgsql'),
            ]);
            DB::purge($connectionName);
        }

        $user = User::on($workerName)->create([
            'name'     => 'Concurrency User',
            'email'    => 'concurrency-user-'.Str::lower(Str::random(10)).'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $organization = Organization::on($workerName)->create([
            'name'          => 'Concurrency Org',
            'slug'          => 'concurrency-org-'.Str::lower(Str::random(10)),
            'type'          => Organization::TYPE_BUSINESS,
            'status'        => Organization::STATUS_ACTIVE,
            'owner_user_id' => $user->id,
        ]);

        $provider = Provider::on($workerName)->create([
            'organization_id' => $organization->id,
            'type'            => Provider::TYPE_SALON,
            'status'          => 'active',
            'display_name'    => 'Concurrency Provider',
            'slug'            => 'concurrency-provider-'.Str::lower(Str::random(10)),
            'email'           => 'concurrency-'.Str::lower(Str::random(10)).'@example.com',
            'active'          => true,
        ]);

        $originalDefault = DB::getDefaultConnection();
        $holder = DB::connection($holderName);
        $worker = DB::connection($workerName);

        try {
            $holder->beginTransaction();
            $holder->table('providers')->where('id', $provider->id)->lockForUpdate()->first();

            $worker->statement("SET lock_timeout = '750ms'");
            DB::setDefaultConnection($workerName);

            $lockTimedOut = false;

            try {
                app(LocationService::class)->createLocation(
                    Provider::on($workerName)->findOrFail($provider->id),
                    [
                        'name' => 'Blocked First Location',
                        'slug' => 'blocked-first-'.Str::lower(Str::random(8)),
                        'address' => '1 Locked Street',
                        'is_active' => true,
                    ]
                );
            } catch (QueryException $exception) {
                $lockTimedOut = true;
                $this->assertStringContainsString('lock timeout', strtolower($exception->getMessage()));
            } finally {
                DB::setDefaultConnection($originalDefault);
            }

            $this->assertTrue($lockTimedOut, 'The service should wait for the Provider row lock.');
            $this->assertSame(
                0,
                $worker->table('locations')->where('provider_id', $provider->id)->count(),
                'A blocked first-Location transaction must not insert a Location.'
            );

            $holder->rollBack();
            $worker->statement('SET lock_timeout = 0');

            DB::setDefaultConnection($workerName);

            try {
                $first = app(LocationService::class)->createLocation(
                    Provider::on($workerName)->findOrFail($provider->id),
                    [
                        'name' => 'First After Lock Release',
                        'slug' => 'first-after-lock-'.Str::lower(Str::random(8)),
                        'address' => '2 Released Street',
                        'is_active' => true,
                    ]
                );

                $second = app(LocationService::class)->createLocation(
                    Provider::on($workerName)->findOrFail($provider->id),
                    [
                        'name' => 'Second After Lock Release',
                        'slug' => 'second-after-lock-'.Str::lower(Str::random(8)),
                        'address' => '3 Released Street',
                        'is_active' => true,
                    ]
                );
            } finally {
                DB::setDefaultConnection($originalDefault);
            }

            $this->assertTrue($first->fresh()->is_primary);
            $this->assertFalse($second->fresh()->is_primary);
            $this->assertSame(1, Location::on($workerName)
                ->where('provider_id', $provider->id)
                ->where('is_primary', true)
                ->count());
        } finally {
            DB::setDefaultConnection($originalDefault);

            if ($holder->transactionLevel() > 0) {
                $holder->rollBack();
            }

            if (isset($provider)) {
                $worker->table('locations')->where('provider_id', $provider->id)->delete();
                $worker->table('providers')->where('id', $provider->id)->delete();
            }
            if (isset($organization)) {
                $worker->table('organizations')->where('id', $organization->id)->delete();
            }
            if (isset($user)) {
                $worker->table('users')->where('id', $user->id)->delete();
            }

            DB::purge($holderName);
            DB::purge($workerName);
        }
    }
}
