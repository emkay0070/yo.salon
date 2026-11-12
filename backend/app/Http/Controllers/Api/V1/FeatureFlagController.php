<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CapabilityService;
use App\Models\Provider;
use App\Models\FeatureFlag;
use App\Models\FeaturePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FeatureFlagController extends Controller
{
    protected CapabilityService $capabilityService;

    public function __construct(CapabilityService $capabilityService)
    {
        $this->capabilityService = $capabilityService;
    }

    /**
     * Get all features and their status for a provider
     */
    public function index(Request $request): JsonResponse
    {
        $providerId = $request->query('provider_id');
        
        if (!$providerId) {
            return response()->json([
                'message' => 'provider_id parameter is required',
            ], 400);
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json([
                'message' => 'Provider not found',
            ], 404);
        }

        $allFeatures = [
            'wallet',
            'loyalty',
            'gift_cards',
            'packages',
            'membership',
            'offers',
            'reviews',
            'support',
            'waitlist',
            'referrals',
            'subscriptions',
            'ai_recommendations',
            'my_stylist',
            'rebook',
            'service_categories',
            'staff_profiles',
        ];

        $featureStatus = [];
        foreach ($allFeatures as $feature) {
            $flag = FeatureFlag::where('provider_id', $providerId)
                ->where('feature_key', $feature)
                ->first();

            $featureStatus[] = [
                'feature_key' => $feature,
                'enabled' => $flag ? $flag->enabled : false,
                'enabled_at' => $flag?->enabled_at,
                'enabled_by' => $flag?->enabledBy?->name,
                'reason' => $flag?->reason,
                'available' => $this->capabilityService->allows($provider, $feature),
            ];
        }

        return response()->json([
            'provider_id' => $providerId,
            'provider_name' => $provider->display_name,
            'features' => $featureStatus,
            'available_features' => $this->capabilityService->getAvailableFeatures($provider),
            'suggested_features' => $this->capabilityService->suggestFeatures($provider),
        ]);
    }

    /**
     * Enable a feature for a provider
     */
    public function enable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid',
            'feature_key' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $provider = Provider::find($validated['provider_id']);
        if (!$provider) {
            return response()->json([
                'message' => 'Provider not found',
            ], 404);
        }

        try {
            $this->capabilityService->enableFeature(
                $provider,
                $validated['feature_key'],
                $validated['reason'] ?? 'Manually enabled via admin'
            );

            return response()->json([
                'message' => 'Feature enabled successfully',
                'feature_key' => $validated['feature_key'],
                'provider_id' => $validated['provider_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to enable feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable a feature for a provider
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid',
            'feature_key' => 'required|string',
        ]);

        $provider = Provider::find($validated['provider_id']);
        if (!$provider) {
            return response()->json([
                'message' => 'Provider not found',
            ], 404);
        }

        try {
            $this->capabilityService->disableFeature($provider, $validated['feature_key']);

            return response()->json([
                'message' => 'Feature disabled successfully',
                'feature_key' => $validated['feature_key'],
                'provider_id' => $validated['provider_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to disable feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all feature policies
     */
    public function policies(): JsonResponse
    {
        $policies = FeaturePolicy::where('active', true)->get();
        
        return response()->json([
            'policies' => $policies,
        ]);
    }

    /**
     * Seed default feature policies
     */
    public function seedPolicies(): JsonResponse
    {
        try {
            FeaturePolicy::seedDefaultPolicies();

            return response()->json([
                'message' => 'Default policies seeded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to seed policies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get suggested features for a provider
     */
    public function suggestions(Request $request): JsonResponse
    {
        $providerId = $request->query('provider_id');
        
        if (!$providerId) {
            return response()->json([
                'message' => 'provider_id parameter is required',
            ], 400);
        }

        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json([
                'message' => 'Provider not found',
            ], 404);
        }

        $suggestions = $this->capabilityService->suggestFeatures($provider);

        return response()->json([
            'provider_id' => $providerId,
            'suggestions' => $suggestions,
        ]);
    }
}
