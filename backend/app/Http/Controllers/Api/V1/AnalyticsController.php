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
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }
        
        $dto = $this->newIntelligenceEngine->generate($salon);
        
        return response()->json($dto);
    }

    public function index(Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }

        // Use the projection service to compose canonical facts
        $analytics = $this->analyticsProjectionService->compose($salon);

        return response()->json($analytics);
    }
}

