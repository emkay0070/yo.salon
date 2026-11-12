<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Domain\Finance\Compensation\Earning;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompensationEarningController extends CompensationController
{
    /**
     * List earnings for the provider's team.
     */
    public function index(string $providerIdentifier, Request $request): JsonResponse
    {
        $provider = $this->authorizeProviderManager($providerIdentifier);

        $query = Earning::where('provider_type', get_class($provider))
            ->where('provider_id', $provider->id)
            ->with(['compensatable', 'policy', 'period']);

        if ($request->has('compensatable_type') && $request->has('compensatable_id')) {
            $query->where('compensatable_type', $request->query('compensatable_type'))
                  ->where('compensatable_id', $request->query('compensatable_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('period_id')) {
            $query->where('compensation_period_id', $request->query('period_id'));
        }

        $earnings = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($earnings);
    }
}
