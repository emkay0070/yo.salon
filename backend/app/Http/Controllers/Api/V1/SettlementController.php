<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = \App\Domain\Finance\Settlement\Settlement::where('provider_id', $provider->id)
            ->with(['recipient', 'period', 'payouts']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('recipient_id')) {
            $query->where('recipient_id', $request->query('recipient_id'));
        }

        return response()->json($query->latest()->get());
    }

    public function show($id): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settlement = \App\Domain\Finance\Settlement\Settlement::where('provider_id', $provider->id)
            ->with(['recipient', 'period', 'earnings', 'payouts'])
            ->findOrFail($id);

        return response()->json($settlement);
    }
}
