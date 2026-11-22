<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RlsContext
 *
 * Helper for executing operations under PostgreSQL Row-Level Security (RLS)
 * on the `pgsql_tybuel` runtime connection.
 *
 * Sequence (PgBouncer transaction-pooling safe):
 *   1. Begin transaction on pgsql_tybuel
 *   2. Set organization context inside transaction (is_local = true)
 *   3. Set default database connection to pgsql_tybuel so Eloquent models route to it
 *   4. Execute callback / queries
 *   5. Clear context before commit
 *   6. Commit transaction (or rollback on error)
 *   7. Restore previous default connection in finally block
 */
class RlsContext
{
    public const TYBUEL_CONNECTION = 'pgsql_tybuel';
    public const SESSION_VAR = 'app.current_organization_id';

    /**
     * Preserved default connection before manual set() call.
     */
    private static ?string $previousDefaultConnection = null;

    /**
     * Execute a callback inside an RLS-scoped transaction on pgsql_tybuel.
     *
     * @param  string    $orgId     Organization UUID, or '' for public discovery.
     * @param  callable  $callback  The work to execute inside the tenant context.
     * @return mixed                The return value of the callback.
     * @throws \Throwable           Re-throws any exception after rollback.
     */
    public static function run(string $orgId, callable $callback): mixed
    {
        $previousDefault = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection(self::TYBUEL_CONNECTION);
            $conn = DB::connection(self::TYBUEL_CONNECTION);

            // Step 1: Begin transaction
            $conn->beginTransaction();

            // Step 2: Set Organization context inside transaction (is_local = true)
            $conn->statement(
                "SELECT set_config('" . self::SESSION_VAR . "', ?, true)",
                [$orgId]
            );

            // Step 3: Execute application callback
            $result = $callback();

            // Step 4: Clear context before committing
            $conn->statement("SELECT set_config('" . self::SESSION_VAR . "', '', true)");

            // Step 5: Commit transaction
            $conn->commit();

            return $result;

        } catch (\Throwable $e) {
            try {
                if (isset($conn) && $conn->transactionLevel() > 0) {
                    $conn->statement("SELECT set_config('" . self::SESSION_VAR . "', '', true)");
                    $conn->rollBack();
                }
            } catch (\Throwable $rollbackEx) {
                Log::warning('RlsContext::run rollback failed.', ['error' => $rollbackEx->getMessage()]);
            }

            throw $e;

        } finally {
            DB::setDefaultConnection($previousDefault);
        }
    }

    /**
     * Manual context setter for test setups or interactive sessions.
     * Begins transaction on pgsql_tybuel if not already active and sets context.
     */
    public static function set(string $orgId): void
    {
        if (self::$previousDefaultConnection === null) {
            self::$previousDefaultConnection = DB::getDefaultConnection();
        }

        DB::setDefaultConnection(self::TYBUEL_CONNECTION);
        $conn = DB::connection(self::TYBUEL_CONNECTION);

        if ($conn->transactionLevel() === 0) {
            $conn->beginTransaction();
        }

        $conn->statement(
            "SELECT set_config('" . self::SESSION_VAR . "', ?, true)",
            [$orgId]
        );
    }

    /**
     * Manual context clearer. Clears context, ends active transaction,
     * and restores previous default connection.
     */
    public static function clear(): void
    {
        try {
            $conn = DB::connection(self::TYBUEL_CONNECTION);
            if ($conn->transactionLevel() > 0) {
                $conn->statement("SELECT set_config('" . self::SESSION_VAR . "', '', true)");
                $conn->commit();
            }
        } catch (\Throwable $e) {
            Log::warning('RlsContext::clear failed.', ['error' => $e->getMessage()]);
        } finally {
            if (self::$previousDefaultConnection !== null) {
                DB::setDefaultConnection(self::$previousDefaultConnection);
                self::$previousDefaultConnection = null;
            }
        }
    }

    /**
     * Read the active organization context from the current session/transaction.
     */
    public static function current(): string
    {
        try {
            $conn = DB::connection(self::TYBUEL_CONNECTION);
            $row = $conn->selectOne(
                "SELECT current_setting('" . self::SESSION_VAR . "', true) AS org_id"
            );
            return (string) ($row->org_id ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
