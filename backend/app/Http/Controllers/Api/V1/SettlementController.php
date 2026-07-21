<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $query = Settlement::with(['paymentMethod'])
            ->where('salon_id', $salonId);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->latest('scheduled_for')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        $validated = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount'            => 'required|numeric|min:1',
            'currency'          => 'sometimes|string|max:10',
            'status'            => 'sometimes|in:pending,completed,failed',
            'reference'         => 'nullable|string|max:100',
            'notes'             => 'nullable|string',
            'scheduled_for'     => 'required|date',
        ]);

        $validated['salon_id'] = $salonId;

        $settlement = Settlement::create($validated);
        return response()->json($settlement->load('paymentMethod'), 201);
    }

    public function show(Settlement $settlement): JsonResponse
    {
        return response()->json($settlement->load(['paymentMethod', 'salon']));
    }

    public function update(Request $request, Settlement $settlement): JsonResponse
    {
        $validated = $request->validate([
            'status'       => 'sometimes|in:pending,completed,failed',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
            'completed_at' => 'nullable|date',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = $validated['completed_at'] ?? now();
        }

        $settlement->update($validated);
        return response()->json($settlement->load('paymentMethod'));
    }
}
