<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locationId = $request->attributes->get('location_id');

        if (!$locationId) {
            return response()->json([
                'message' => 'Location context not found',
            ], 400);
        }

        $resources = Resource::with(['capabilities', 'exceptions'])
            ->where('location_id', $locationId)
            ->get();

        return response()->json($resources);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|uuid|exists:locations,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'capacity' => 'integer|min:1',
            'turnaround_time' => 'integer|min:0',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        // Verify the authenticated user owns the provider that owns this location
        $user = $request->user();
        $location = \App\Models\Location::find($validated['location_id']);
        if (!$location) {
            return response()->json([
                'message' => 'Invalid location',
            ], 422);
        }

        // Check if user owns the provider
        $userProviderIds = $user->providers()->pluck('providers.id')->toArray();
        if (!in_array($location->provider_id, $userProviderIds)) {
            return response()->json([
                'message' => 'Unauthorized: you do not own this location',
            ], 403);
        }

        $resource = Resource::create($validated);

        return response()->json($resource, 201);
    }

    public function show(string $id): JsonResponse
    {
        $resource = Resource::with(['capabilities', 'exceptions'])->findOrFail($id);

        return response()->json($resource);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:100',
            'capacity' => 'sometimes|integer|min:1',
            'turnaround_time' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        $resource->update($validated);

        return response()->json($resource);
    }

    public function destroy(string $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $resource->delete();

        return response()->json(null, 204);
    }
}
