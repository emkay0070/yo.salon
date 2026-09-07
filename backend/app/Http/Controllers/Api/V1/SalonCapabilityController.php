<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalonCapabilityController extends Controller
{
    public function __construct(
        private readonly CapabilityResolver $capabilityResolver
    ) {}

    /**
     * Get comprehensive capability summary for a salon.
     * 
     * GET /api/v1/salons/{id}/capabilities
     * 
     * Returns plan, features, resources (quotas), and credits information.
     * This is the single source of truth for frontend capability checks.
     */
    public function show(string $salonId): JsonResponse
    {
        try {
            $capabilities = $this->capabilityResolver->getCapabilitiesSummary($salonId);
            
            return response()->json($capabilities);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve capabilities',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a specific feature is enabled for a salon.
     * 
     * GET /api/v1/salons/{id}/capabilities/feature/{featureCode}
     * 
     * Example: /api/v1/salons/{id}/capabilities/feature/CALENDAR_WEEK
     */
    public function checkFeature(string $salonId, string $featureCode): JsonResponse
    {
        try {
            $allowed = $this->capabilityResolver->allows($salonId, $featureCode);
            
            return response()->json([
                'feature' => $featureCode,
                'allowed' => $allowed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check feature',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get quota information for a specific resource.
     * 
     * GET /api/v1/salons/{id}/capabilities/quota/{resourceCode}
     * 
     * Example: /api/v1/salons/{id}/capabilities/quota/STAFF_SEAT
     */
    public function getQuota(string $salonId, string $resourceCode): JsonResponse
    {
        try {
            return response()->json([
                'resource' => $resourceCode,
                'used' => $this->capabilityResolver->usage($salonId, $resourceCode),
                'limit' => $this->capabilityResolver->limit($salonId, $resourceCode),
                'remaining' => $this->capabilityResolver->remaining($salonId, $resourceCode),
                'can_add' => $this->capabilityResolver->canAdd($salonId, $resourceCode),
                'is_over_limit' => $this->capabilityResolver->isOverLimit($salonId, $resourceCode),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get quota',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get credit balance for a specific credit type.
     * 
     * GET /api/v1/salons/{id}/capabilities/credits/{creditCode}
     * 
     * Example: /api/v1/salons/{id}/capabilities/credits/SMS
     */
    public function getCredits(string $salonId, string $creditCode): JsonResponse
    {
        try {
            return response()->json([
                'credit' => $creditCode,
                'available' => $this->capabilityResolver->credits($salonId, $creditCode),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get credits',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
