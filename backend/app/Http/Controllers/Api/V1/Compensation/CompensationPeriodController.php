<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Compensation\CompensationPeriod;
use App\Domain\Finance\Compensation\PeriodClosingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompensationPeriodController extends CompensationController
{
    /**
     * List open and closed periods.
     */
    public function index(string $providerIdentifier, Request $request): JsonResponse
    {
        $provider = $this->authorizeProviderManager($providerIdentifier);

        $query = CompensationPeriod::where('provider_type', get_class($provider))
            ->where('provider_id', $provider->id)
            ->with(['compensatable']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('compensatable_type') && $request->has('compensatable_id')) {
            $query->where('compensatable_type', $request->query('compensatable_type'))
                  ->where('compensatable_id', $request->query('compensatable_id'));
        }

        $periods = $query->orderBy('period_start', 'desc')->get();

        return response()->json($periods);
    }

    /**
     * View detailed period.
     */
    public function show(string $providerIdentifier, string $periodId): JsonResponse
    {
        $provider = $this->authorizeProviderManager($providerIdentifier);

        $period = CompensationPeriod::where('provider_type', get_class($provider))
            ->where('provider_id', $provider->id)
            ->where('id', $periodId)
            ->with(['compensatable', 'earnings', 'adjustments', 'policy'])
            ->firstOrFail();

        return response()->json($period);
    }

    /**
     * Manually close a period.
     */
    public function close(string $providerIdentifier, string $periodId, PeriodClosingService $closingService): JsonResponse
    {
        $provider = $this->authorizeProviderManager($providerIdentifier);

        $period = CompensationPeriod::where('provider_type', get_class($provider))
            ->where('provider_id', $provider->id)
            ->where('id', $periodId)
            ->firstOrFail();

        try {
            $closingService->closePeriod($period);
            return response()->json($period->fresh(['earnings', 'adjustments']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
