<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application and migrate the test database without dropping tables.
     *
     * Test classes use Laravel's RefreshDatabase trait directly. In PHP, a trait
     * method used by the child class takes precedence over an inherited parent
     * method, so overriding refreshTestDatabase() or migrateDatabases() on this
     * class would be bypassed. Instead, migrate once before the trait runs and
     * mark the state as migrated; the trait then performs its normal per-test
     * transaction start and rollback lifecycle.
     *
     * `migrate` is used instead of Laravel's default `migrate:fresh` because
     * PostgreSQL can fail when DROP TABLE ... CASCADE drops a child table before
     * the loop reaches it. The test database is created empty, so `migrate`
     * creates the full schema on the first test and is a no-op thereafter.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (isset($this->traitsUsedByTest[RefreshDatabase::class])) {
            $this->migrateTestDatabaseOnce($app);
        }

        return $app;
    }

    /**
     * Run pending migrations once per test process.
     */
    private function migrateTestDatabaseOnce(Application $app): void
    {
        if (RefreshDatabaseState::$migrated) {
            return;
        }

        $seeder = $this->seeder();
        $parameters = [];

        if ($seeder || $this->shouldSeed()) {
            $parameters['--seed'] = true;
        }

        if ($seeder) {
            $parameters['--seeder'] = $seeder;
        }

        $kernel = $app->make(Kernel::class);
        $kernel->call('migrate', $parameters);
        $kernel->setArtisan(null);

        RefreshDatabaseState::$migrated = true;
    }
}
