<?php

namespace App\Http\Middleware;

use Closure;
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
 *   1. Begin transaction on `pgsql_tybuel`.
 *      In PgBouncer transaction-pooling mode, BEGIN pins a backend connection.
 *   2. Set Organization context INSIDE that transaction (is_local = true):
 *      SELECT set_config('app.current_organization_id', ?, true)
 *   3. Switch default connection to `pgsql_tybuel` so all downstream Eloquent
 *      models and queries automatically execute against `pgsql_tybuel`.
 *   4. Execute downstream middleware and controller actions ($next($request)).
 *   5. Defensively clear context before committing:
 *      SELECT set_config('app.current_organization_id', '', true)
 *   6. End transaction (commit).
 *      (If an exception occurs: clear context, roll back transaction, and rethrow).
 *   7. In finally block: restore previous default database connection.
 *
 * Failure policy:
 *   Universal FAIL CLOSED across all environments. If the connection cannot be
 *   established, transaction cannot begin, or context cannot be set, the request
 *   aborts immediately with HTTP 500. No queries are permitted to run without context.
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

        try {
            // Ensure default connection routes all model queries to pgsql_tybuel
            DB::setDefaultConnection(self::TYBUEL_CONNECTION);
            $connection = DB::connection(self::TYBUEL_CONNECTION);

            // Step 1: Begin transaction on pgsql_tybuel
            $connection->beginTransaction();

            // Step 2: Set Organization context INSIDE that transaction (is_local = true)
            $connection->statement(
                "SELECT set_config('app.current_organization_id', ?, true)",
                [$orgId]
            );

        } catch (\Throwable $e) {
            // Context initialization failure — MUST fail closed in EVERY environment
            Log::error('RLS context initialization failed — request aborted.', [
                'connection' => self::TYBUEL_CONNECTION,
                'has_org_id' => !empty($orgId),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            DB::setDefaultConnection($previousDefault);

            abort(500, 'Tenant security context could not be established.');
        }

        // Step 3: Execute all protected queries on that same connection inside transaction
        try {
            $response = $next($request);

            // Step 4: Clear context before committing
            $connection->statement("SELECT set_config('app.current_organization_id', '', true)");

            // Step 5: End the transaction (commit)
            $connection->commit();

            return $response;

        } catch (\Throwable $e) {
            // On failure: clear context before rolling back
            try {
                if ($connection->transactionLevel() > 0) {
                    $connection->statement("SELECT set_config('app.current_organization_id', '', true)");
                    $connection->rollBack();
                }
            } catch (\Throwable $rollbackEx) {
                Log::warning('RLS rollback failed after exception.', [
                    'error' => $rollbackEx->getMessage(),
                ]);
            }

            throw $e;

        } finally {
            // Restore default connection for clean connection-pool recycling
            DB::setDefaultConnection($previousDefault);
        }
    }
}
