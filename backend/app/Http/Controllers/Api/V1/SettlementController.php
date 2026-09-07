<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = \App\Domain\Finance\Settlement\Settlement::where('payable_type', \App\Models\Salon::class)
            ->where('payable_id', $salon->id)
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
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settlement = \App\Domain\Finance\Settlement\Settlement::where('payable_type', \App\Models\Salon::class)
            ->where('payable_id', $salon->id)
            ->with(['recipient', 'period', 'earnings', 'payouts'])
            ->findOrFail($id);

        return response()->json($settlement);
    }
}
