<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResourceCapability;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResourceCapabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $capabilities = ResourceCapability::where('resource_id', $request->resource_id)->get();

        return response()->json($capabilities);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => 'required|uuid|exists:resources,id',
            'capability' => 'required|string|max:100',
            'level' => 'nullable|string|max:50',
        ]);

        $capability = ResourceCapability::create($validated);

        return response()->json($capability, 201);
    }

    public function show(string $id): JsonResponse
    {
        $capability = ResourceCapability::findOrFail($id);

        return response()->json($capability);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $capability = ResourceCapability::findOrFail($id);

        $validated = $request->validate([
            'capability' => 'sometimes|string|max:100',
            'level' => 'nullable|string|max:50',
        ]);

        $capability->update($validated);

        return response()->json($capability);
    }

    public function destroy(string $id): JsonResponse
    {
        $capability = ResourceCapability::findOrFail($id);
        $capability->delete();

        return response()->json(null, 204);
    }
}
