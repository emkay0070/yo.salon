<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Location;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LocationSpecialistController extends Controller
{
    public function index(Request $request, string $providerId, ?string $locationId = null): JsonResponse
    {
        // Verify provider exists
        $provider = Provider::find($providerId);
        if (!$provider) {
            \Log::error("Provider not found for specialists query", ['providerId' => $providerId]);
            return response()->json(['message' => 'Provider not found'], 404);
        }

        // Verify location exists if provided
        if ($locationId) {
            $location = Location::where('id', $locationId)->where('provider_id', $providerId)->first();
            if (!$location) {
                \Log::error("Location not found for provider", ['providerId' => $providerId, 'locationId' => $locationId]);
                return response()->json(['message' => 'Location not found'], 404);
            }
        }

        // Use SpecialistAssignment to find specialists at this provider/location
        $query = SpecialistAssignment::where('provider_id', $providerId);
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        $query = $query
            ->where('status', 'ACTIVE')
            ->with('specialist');

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', strtoupper($request->query('role')));
        }

        $assignments = $query->orderBy('created_at', 'desc')->get()->unique('specialist_id');

        \Log::info("Specialist assignments found", ['providerId' => $providerId, 'locationId' => $locationId, 'count' => $assignments->count()]);

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

    public function store(Request $request, string $providerId, ?string $locationId = null): JsonResponse
    {
        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        if ($locationId) {
            $location = Location::where('id', $locationId)->where('provider_id', $providerId)->first();
            if (!$location) {
                return response()->json(['message' => 'Location not found'], 404);
            }
        }

        $validated = $request->validate([
            'specialist_id' => 'required|exists:specialists,id',
            'role' => 'sometimes|string|in:OWNER,MANAGER,SENIOR_SPECIALIST,SPECIALIST,TRAINEE',
            'employment_type' => 'sometimes|string|in:EMPLOYEE,FREELANCER,CONTRACTOR',
            'is_primary' => 'sometimes|boolean',
            'commission_rate' => 'sometimes|numeric',
            'hourly_rate' => 'sometimes|numeric',
            'status' => 'sometimes|string|in:ACTIVE,INACTIVE,TERMINATED',
        ]);

        $specialist = Specialist::find($validated['specialist_id']);
        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Check if assignment already exists for this provider + location
        $existing = SpecialistAssignment::where('specialist_id', $specialist->id)
            ->where('provider_id', $providerId)
            ->when($locationId, fn($q) => $q->where('location_id', $locationId))
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Specialist already has an assignment at this location'], 409);
        }

        // Create SpecialistAssignment
        $assignment = SpecialistAssignment::create([
            'specialist_id' => $specialist->id,
            'provider_id' => $providerId,
            'location_id' => $locationId,
            'role' => $validated['role'] ?? 'SPECIALIST',
            'employment_type' => $validated['employment_type'] ?? 'EMPLOYEE',
            'is_primary' => $validated['is_primary'] ?? false,
            'commission_rate' => $validated['commission_rate'] ?? null,
            'hourly_rate' => $validated['hourly_rate'] ?? null,
            'status' => $validated['status'] ?? 'ACTIVE',
        ]);

        return response()->json([
            'specialist' => $specialist,
            'assignment' => $assignment->load('specialist')
        ], 201);
    }

    public function show(Request $request, string $providerId, ?string $locationId = null, string $specialistId): JsonResponse
    {
        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        if ($locationId) {
            $location = Location::where('id', $locationId)->where('provider_id', $providerId)->first();
            if (!$location) {
                return response()->json(['message' => 'Location not found'], 404);
            }
        }

        // Use SpecialistAssignment to find the specialist at this location
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('provider_id', $providerId)
            ->when($locationId, fn($q) => $q->where('location_id', $locationId))
            ->where('status', 'ACTIVE')
            ->with('specialist')
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Specialist not found at this location'], 404);
        }

        $specialist = $assignment->specialist;
        $specialist->pivot = (object)[
            'role' => $assignment->role,
            'employment_type' => $assignment->employment_type,
            'is_primary' => $assignment->is_primary,
            'status' => $assignment->status,
            'assignment_id' => $assignment->id,
        ];

        return response()->json($specialist);
    }

    public function update(Request $request, string $providerId, ?string $locationId = null, string $specialistId): JsonResponse
    {
        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        if ($locationId) {
            $location = Location::where('id', $locationId)->where('provider_id', $providerId)->first();
            if (!$location) {
                return response()->json(['message' => 'Location not found'], 404);
            }
        }

        $validated = $request->validate([
            'role' => 'sometimes|string|in:OWNER,MANAGER,SENIOR_SPECIALIST,SPECIALIST,TRAINEE',
            'employment_type' => 'sometimes|string|in:EMPLOYEE,FREELANCER,CONTRACTOR',
            'is_primary' => 'sometimes|boolean',
            'commission_rate' => 'sometimes|numeric',
            'hourly_rate' => 'sometimes|numeric',
            'status' => 'sometimes|string|in:ACTIVE,INACTIVE,TERMINATED',
        ]);

        // Find and update the SpecialistAssignment
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('provider_id', $providerId)
            ->when($locationId, fn($q) => $q->where('location_id', $locationId))
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Specialist assignment not found at this location'], 404);
        }

        $assignment->update($validated);

        $specialist = $assignment->specialist;
        $specialist->pivot = (object)[
            'role' => $assignment->role,
            'employment_type' => $assignment->employment_type,
            'is_primary' => $assignment->is_primary,
            'status' => $assignment->status,
            'assignment_id' => $assignment->id,
        ];

        return response()->json($specialist);
    }

    public function destroy(string $providerId, ?string $locationId = null, string $specialistId): JsonResponse
    {
        $provider = Provider::find($providerId);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        if ($locationId) {
            $location = Location::where('id', $locationId)->where('provider_id', $providerId)->first();
            if (!$location) {
                return response()->json(['message' => 'Location not found'], 404);
            }
        }

        // Find and delete the SpecialistAssignment
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('provider_id', $providerId)
            ->when($locationId, fn($q) => $q->where('location_id', $locationId))
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Specialist assignment not found at this location'], 404);
        }

        $assignment->delete();

        return response()->json(null, 204);
    }
}
