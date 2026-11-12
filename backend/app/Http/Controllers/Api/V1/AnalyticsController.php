<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsProjectionService;
use App\Services\Intelligence\IntelligenceEngine as NewIntelligenceEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected AnalyticsProjectionService $analyticsProjectionService;
    protected NewIntelligenceEngine $newIntelligenceEngine;

    public function __construct(
        AnalyticsProjectionService $analyticsProjectionService,
        NewIntelligenceEngine $newIntelligenceEngine
    ) {
        $this->analyticsProjectionService = $analyticsProjectionService;
        $this->newIntelligenceEngine = $newIntelligenceEngine;
    }

    public function intelligence(Request $request): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) {
            return response()->json(['error' => 'Provider not found'], 404);
        }

        $locationId = $request->attributes->get('location_id');
        $locationScope = $request->attributes->get('location_scope', 'all');

        // Pass location context to intelligence engine for location-scoped analytics
        // If location_scope is 'specific', filter by location_id
        // If location_scope is 'all', provide Provider-wide analytics
        $location = null;
        if ($locationScope === 'specific' && $locationId) {
            $location = \App\Models\Location::find($locationId);
        }
        $dto = $this->newIntelligenceEngine->generate($provider, $location);

        return response()->json($dto);
    }

    public function index(Request $request): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) {
            return response()->json(['error' => 'Provider not found'], 404);
        }

        $locationId = $request->attributes->get('location_id');
        $locationScope = $request->attributes->get('location_scope', 'all');

        // Use the projection service to compose canonical facts
        // Pass location context for location-scoped analytics
        $location = null;
        if ($locationScope === 'specific' && $locationId) {
            $location = \App\Models\Location::find($locationId);
        }
        $analytics = $this->analyticsProjectionService->compose($provider, $location);

        return response()->json($analytics);
    }
}

