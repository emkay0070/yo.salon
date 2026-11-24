<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SetOrganizationDatabaseContext
 *
 * Implements strict transaction-local PostgreSQL Row-Level Security (RLS)
 * context management on the `pgsql_tybuel` runtime connection.
 *
 * Execution sequence (PgBouncer transaction-pooling safe):
 *   1. Begin transaction on `pgsql_tybuel` if we are not already inside one.
 *      In PgBouncer transaction-pooling mode, BEGIN pins a backend connection.
 *   2. Set Organization context INSIDE that transaction (is_local = true):
 *      SELECT set_config('app.current_organization_id', ?, true)
 *   3. Switch default connection to `pgsql_tybuel` so all downstream Eloquent
 *      models and queries automatically execute against `pgsql_tybuel`.
 *   4. Execute downstream middleware and controller actions ($next($request)).
 *   5. Commit the transaction if WE started it.
 *      (Transaction-local set_config is discarded automatically on commit.)
 *   6. In finally block: restore previous default database connection.
 *
 * Failure policy:
 *   Universal FAIL CLOSED across all environments. If the connection cannot be
 *   established, transaction cannot begin, or context cannot be set, the request
 *   aborts immediately with HTTP 500. No queries are permitted to run without
 *   context.
 *
 * Transaction ownership:
 *   The middleware ONLY begins, commits or rolls back a transaction it started
 *   itself ($startedTransaction === true). A transaction owned by another
 *   component (e.g. a test harness wrapping the request in RefreshDatabase) is
 *   never touched by this middleware.
 *
 * Aborted-transaction safety:
 *   If a statement fails inside the active transaction, PostgreSQL aborts the
 *   transaction. In that state only ROLLBACK is permitted; any other statement
 *   (including set_config) raises "cannot execute ... in a failed transaction".
 *   Therefore the error path never issues set_config cleanup — it simply rolls
 *   back the transaction WE started, which automatically discards the
 *   transaction-local RLS context. This guarantees the connection is never left
 *   open or in an aborted state for the next pooled request.
 */
class SetOrganizationDatabaseContext
{
    /**
     * The runtime connection with NOBYPASSRLS and NO table ownership.
     */
    public const TYBUEL_CONNECTION = 'pgsql_tybuel';

    public function handle(Request $request, Closure $next): mixed
    {
        // Resolve organization_id from request attributes (set by ResolveOrganizationContext).
        // For unauthenticated/guest requests, this is an empty string ''.
        $orgId = (string) $request->attributes->get('organization_id', '');

        $previousDefault = DB::getDefaultConnection();
        $connectionName = app()->environment('testing') ? $previousDefault : self::TYBUEL_CONNECTION;
        $startedTransaction = false;
        $connection = null;

        // -----------------------------------------------------------------
        // Step 1 & 2: Begin transaction (if we own it) and set RLS context.
        // -----------------------------------------------------------------
        try {
            // Ensure default connection routes all model queries to the target connection
            DB::setDefaultConnection($connectionName);
            $connection = DB::connection($connectionName);

            // Begin transaction if not already inside one (e.g. testing with RefreshDatabase).
            // Only the component that begins a transaction may commit or roll it back.
            if ($connection->transactionLevel() === 0) {
                $connection->beginTransaction();
                $startedTransaction = true;
            }

            // Set Organization context INSIDE that transaction (is_local = true).
            // Transaction-local: automatically discarded on commit or rollback.
            $connection->statement(
                "SELECT set_config('app.current_organization_id', ?, true)",
                [$orgId]
            );

        } catch (\Throwable $e) {
            // Context initialization failure — MUST fail closed in EVERY environment.
            // Roll back the transaction WE started (if any) so the connection is
            // not left open or aborted for the next pooled request. Never roll back
            // a transaction owned by another component.
            if ($startedTransaction && $connection && $connection->transactionLevel() > 0) {
                try {
                    $connection->rollBack();
                } catch (\Throwable $rollbackEx) {
                    Log::warning('RLS rollback failed after context initialization exception.', [
                        'error' => $rollbackEx->getMessage(),
                    ]);
                }
            }

            Log::error('RLS context initialization failed — request aborted.', [
                'connection' => $connectionName,
                'has_org_id' => !empty($orgId),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            DB::setDefaultConnection($previousDefault);

            abort(500, 'Tenant security context could not be established.');
        }

        // The authenticated principal may have been hydrated on a different
        // connection before this context existed (e.g. by auth:sanctum on the
        // default connection). Eloquent pins a model instance to the connection
        // it was loaded from, so rebind it here — otherwise its tenant-scoped
        // relations keep querying the context-less connection and resolve to
        // nothing under RLS (e.g. provider access checks returning false).
        $principal = $request->user();
        if ($principal instanceof Model) {
            $principal->setConnection($connectionName);
        }

        // -----------------------------------------------------------------
        // Step 3 & 4: Execute all protected queries on that same connection
        // inside the transaction.
        // -----------------------------------------------------------------
        try {
            $response = $next($request);

            // Success path.
            // Clear the transaction-local context before committing (belt and
            // suspenders; commit automatically restores the previous value).
            // Only safe because no exception occurred, so the transaction is
            // not in an aborted state.
            try {
                if ($connection && $connection->transactionLevel() > 0) {
                    $connection->statement("SELECT set_config('app.current_organization_id', '', true)");
                }
            } catch (\Throwable $clearEx) {
                Log::warning('RLS context clear failed before commit.', [
                    'error' => $clearEx->getMessage(),
                ]);
            }

            // Commit the transaction if WE started it.
            if ($startedTransaction && $connection && $connection->transactionLevel() > 0) {
                $connection->commit();
            }

            return $response;

        } catch (\Throwable $e) {
            // Failure path: roll back the transaction WE started, safely.
            // Do NOT attempt set_config() cleanup here: if the failing statement
            // aborted the PostgreSQL transaction, issuing set_config would raise
            // "cannot execute SET in a failed transaction". Transaction-local
            // RLS context is discarded automatically by rollback.
            if ($startedTransaction && $connection && $connection->transactionLevel() > 0) {
                try {
                    $connection->rollBack();
                } catch (\Throwable $rollbackEx) {
                    Log::warning('RLS rollback failed after request exception.', [
                        'error' => $rollbackEx->getMessage(),
                    ]);
                }
            }

            throw $e;

        } finally {
            // Step 6: Restore default connection for clean connection-pool recycling.
            DB::setDefaultConnection($previousDefault);
        }
    }
}
