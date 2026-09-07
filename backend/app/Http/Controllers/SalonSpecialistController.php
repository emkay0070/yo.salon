<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalonSpecialistController extends Controller
{
    public function index(Request $request, string $salonId): JsonResponse
    {
        // Verify salon exists
        $salon = Salon::find($salonId);
        if (!$salon) {
            \Log::error("Salon not found for specialists query", ['salonId' => $salonId]);
            return response()->json(['message' => 'Salon not found'], 404);
        }

        // Use SpecialistAssignment instead of legacy salon_specialist pivot
        $query = SpecialistAssignment::where('salon_id', $salonId)
            ->where('status', 'ACTIVE')
            ->with('specialist');

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', strtoupper($request->query('role')));
        }

        $assignments = $query->orderBy('created_at', 'desc')->get()->unique('specialist_id');

        \Log::info("Specialist assignments found", ['salonId' => $salonId, 'count' => $assignments->count()]);

        // Map to specialist format
        $specialists = $assignments->map(function ($assignment) {
            $specialist = $assignment->specialist;
            if ($specialist) {
                $specialist->pivot = (object)[
                    'role' => $assignment->role,
                    'active' => $assignment->status === 'ACTIVE',
                ];
            }
            return $specialist;
        })->filter();

        return response()->json($specialists);
    }

    public function store(Request $request, string $salonId): JsonResponse
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $validated = $request->validate([
            'specialist_id' => 'required|exists:specialists,id',
            'role' => 'sometimes|string|in:staff,receptionist,manager,owner',
            'availability' => 'nullable|array',
            'services' => 'nullable|array',
            'active' => 'sometimes|boolean',
        ]);

        $specialist = Specialist::find($validated['specialist_id']);
        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Check if relationship already exists
        $existing = $salon->specialists()->where('specialist_id', $specialist->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Specialist already linked to this salon'], 409);
        }

        $salon->specialists()->attach($specialist->id, [
            'role' => $validated['role'] ?? 'staff',
            'availability' => $validated['availability'] ?? null,
            'services' => $validated['services'] ?? null,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json($specialist->load('salons'), 201);
    }

    public function show(Request $request, string $salonId, string $specialistId): JsonResponse
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $specialist = $salon->specialists()
            ->where('specialist_id', $specialistId)
            ->where('salon_specialist.active', true)
            ->withPivot('role', 'availability', 'services', 'active')
            ->first();

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found at this salon'], 404);
        }

        return response()->json($specialist);
    }

    public function update(Request $request, string $salonId, string $specialistId): JsonResponse
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|string|in:staff,receptionist,manager,owner',
            'availability' => 'nullable|array',
            'services' => 'nullable|array',
            'active' => 'sometimes|boolean',
        ]);

        $salon->specialists()->updateExistingPivot($specialistId, $validated);

        $specialist = $salon->specialists()
            ->where('specialist_id', $specialistId)
            ->withPivot('role', 'availability', 'services', 'active')
            ->first();

        return response()->json($specialist);
    }

    public function destroy(string $salonId, string $specialistId): JsonResponse
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        $salon->specialists()->detach($specialistId);

        return response()->json(null, 204);
    }
}
