<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Models\Salon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalHealthController extends Controller
{
    private OperationalHealthService $healthService;

    public function __construct(OperationalHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Get operational health for the current salon (branch).
     *
     * GET /api/v1/salons/{salonId}/health
     */
    public function branchHealth(string $salonId): JsonResponse
    {
        try {
            $health = $this->healthService->getBranchHealth($salonId);

            return response()->json([
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve health status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get operational health for a provider.
     *
     * GET /api/v1/providers/{providerId}/health
     */
    public function providerHealth(string $providerId): JsonResponse
    {
        try {
            $health = $this->healthService->getProviderHealth($providerId);

            return response()->json([
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve health status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
