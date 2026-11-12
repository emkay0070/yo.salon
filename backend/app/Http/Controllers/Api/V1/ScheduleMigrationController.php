<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ScheduleMigrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduleMigrationController extends Controller
{
    private ScheduleMigrationService $migrationService;

    public function __construct(ScheduleMigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    /**
     * Check if current provider needs schedule migration
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $user = auth()->user();
        $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;

        if (!$provider) {
            return response()->json(['message' => 'No provider context'], 403);
        }

        $needsMigration = $this->migrationService->needsMigration($provider->id);

        return response()->json([
            'needs_migration' => $needsMigration,
            'provider_id' => $provider->id,
            'provider_name' => $provider->display_name,
        ]);
    }

    /**
     * Trigger migration for current provider
     */
    public function migrate(Request $request): JsonResponse
    {
        $user = auth()->user();
        $provider = method_exists($user, 'currentProvider') ? $user->currentProvider() : null;

        if (!$provider) {
            return response()->json(['message' => 'No provider context'], 403);
        }

        $result = $this->migrationService->migrateProvider($provider->id);

        if ($result['success']) {
            // Mark any related notifications as read
            \App\Models\Notification::where('provider_id', $provider->id)
                ->where('type', 'schedule_migration')
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json($result);
    }

    /**
     * Admin endpoint: Get all providers needing migration
     */
    public function getProvidersNeedingMigration(Request $request): JsonResponse
    {
        // Admin-only check
        $user = auth()->user();
        if (!$user || !$user->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $providers = $this->migrationService->getProvidersNeedingMigration();

        return response()->json([
            'providers' => $providers,
            'count' => count($providers),
        ]);
    }

    /**
     * Admin endpoint: Send migration notifications to all providers
     */
    public function sendNotifications(Request $request): JsonResponse
    {
        // Admin-only check
        $user = auth()->user();
        if (!$user || !$user->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $count = $this->migrationService->notifyAllProvidersNeedingMigration();

        return response()->json([
            'message' => "Notifications sent to {$count} providers",
            'notifications_created' => $count,
        ]);
    }
}
