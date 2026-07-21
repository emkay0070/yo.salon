<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CapabilityService;
use App\Models\Salon;
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
     * Get all features and their status for a salon
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->query('salon_id');
        
        if (!$salonId) {
            return response()->json([
                'message' => 'salon_id parameter is required',
            ], 400);
        }

        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found',
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
            $flag = FeatureFlag::where('salon_id', $salonId)
                ->where('feature_key', $feature)
                ->first();

            $featureStatus[] = [
                'feature_key' => $feature,
                'enabled' => $flag ? $flag->enabled : false,
                'enabled_at' => $flag?->enabled_at,
                'enabled_by' => $flag?->enabledBy?->name,
                'reason' => $flag?->reason,
                'available' => $this->capabilityService->allows($salon, $feature),
            ];
        }

        return response()->json([
            'salon_id' => $salonId,
            'salon_name' => $salon->name,
            'features' => $featureStatus,
            'available_features' => $this->capabilityService->getAvailableFeatures($salon),
            'suggested_features' => $this->capabilityService->suggestFeatures($salon),
        ]);
    }

    /**
     * Enable a feature for a salon
     */
    public function enable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'required|uuid',
            'feature_key' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $salon = Salon::find($validated['salon_id']);
        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found',
            ], 404);
        }

        try {
            $this->capabilityService->enableFeature(
                $salon,
                $validated['feature_key'],
                $validated['reason'] ?? 'Manually enabled via admin'
            );

            return response()->json([
                'message' => 'Feature enabled successfully',
                'feature_key' => $validated['feature_key'],
                'salon_id' => $validated['salon_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to enable feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable a feature for a salon
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'required|uuid',
            'feature_key' => 'required|string',
        ]);

        $salon = Salon::find($validated['salon_id']);
        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found',
            ], 404);
        }

        try {
            $this->capabilityService->disableFeature($salon, $validated['feature_key']);

            return response()->json([
                'message' => 'Feature disabled successfully',
                'feature_key' => $validated['feature_key'],
                'salon_id' => $validated['salon_id'],
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
     * Get suggested features for a salon
     */
    public function suggestions(Request $request): JsonResponse
    {
        $salonId = $request->query('salon_id');
        
        if (!$salonId) {
            return response()->json([
                'message' => 'salon_id parameter is required',
            ], 400);
        }

        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json([
                'message' => 'Salon not found',
            ], 404);
        }

        $suggestions = $this->capabilityService->suggestFeatures($salon);

        return response()->json([
            'salon_id' => $salonId,
            'suggestions' => $suggestions,
        ]);
    }
}
