<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refresh the test database without dropping tables.
     *
     * Laravel's RefreshDatabase trait calls `migrate:fresh`, which runs
     * `DROP TABLE ... CASCADE` without IF EXISTS on all tables. On PostgreSQL,
     * pg_tables returns rows in non-deterministic order, so dropping a parent
     * table cascades to child tables, and the subsequent attempt to drop the
     * already-cascaded child table fails. This leaves the database in an
     * inconsistent state and breaks every subsequent test.
     *
     * Using `migrate` instead of `migrate:fresh` avoids all DROP TABLE
     * operations. The test database is created empty, so migrate creates all
     * 253 tables cleanly. Each test method is still wrapped in a transaction
     * by RefreshDatabase's setUp/tearDown, providing isolation between tests.
     */
    protected function refreshTestDatabase()
    {
        $this->artisan('migrate');
    }
}
