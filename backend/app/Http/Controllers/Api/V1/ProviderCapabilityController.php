<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProviderCapabilityController extends Controller
{
    public function __construct(
        private readonly CapabilityResolver $capabilityResolver
    ) {}

    /**
     * Get comprehensive capability summary for a provider.
     * 
     * GET /api/v1/providers/{id}/capabilities
     * 
     * Returns plan, features, resources (quotas), and credits information.
     * This is the single source of truth for frontend capability checks.
     */
    public function show(string $providerId): JsonResponse
    {
        try {
            $capabilities = $this->capabilityResolver->getCapabilitiesSummary($providerId);
            
            return response()->json($capabilities);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve capabilities',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a specific feature is enabled for a provider.
     * 
     * GET /api/v1/providers/{id}/capabilities/feature/{featureCode}
     * 
     * Example: /api/v1/providers/{id}/capabilities/feature/CALENDAR_WEEK
     */
    public function checkFeature(string $providerId, string $featureCode): JsonResponse
    {
        try {
            $allowed = $this->capabilityResolver->allows($providerId, $featureCode);
            
            return response()->json([
                'allowed' => $allowed,
                'feature' => $featureCode,
                'provider_id' => $providerId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check feature',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get resource quota for a provider.
     * 
     * GET /api/v1/providers/{id}/capabilities/resource/{resourceCode}
     * 
     * Example: /api/v1/providers/{id}/capabilities/resource/STAFF
     */
    public function getResourceQuota(string $providerId, string $resourceCode): JsonResponse
    {
        try {
            $quota = $this->capabilityResolver->getResourceQuota($providerId, $resourceCode);
            $usage = $this->capabilityResolver->getResourceUsage($providerId, $resourceCode);
            
            return response()->json([
                'provider_id' => $providerId,
                'resource_code' => $resourceCode,
                'quota' => $quota,
                'usage' => $usage,
                'remaining' => $quota === -1 ? 'unlimited' : max(0, $quota - $usage),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get resource quota',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get credit balance for a provider.
     * 
     * GET /api/v1/providers/{id}/capabilities/credits/{creditCode}
     * 
     * Example: /api/v1/providers/{id}/capabilities/credits/BOOKING_CREDITS
     */
    public function getCreditBalance(string $providerId, string $creditCode): JsonResponse
    {
        try {
            $balance = $this->capabilityResolver->getCreditBalance($providerId, $creditCode);
            
            return response()->json([
                'provider_id' => $providerId,
                'credit_code' => $creditCode,
                'balance' => $balance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get credit balance',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
