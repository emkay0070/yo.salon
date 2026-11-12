<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ResourceRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResourceRequirementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requirements = ResourceRequirement::where('service_id', $request->service_id)->get();

        return response()->json($requirements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'resource_type' => 'required|string|max:100',
            'required_capabilities' => 'nullable|array',
            'count' => 'integer|min:1',
            'min_capacity' => 'nullable|integer|min:1',
        ]);

        $requirement = ResourceRequirement::create($validated);

        return response()->json($requirement, 201);
    }

    public function show(string $id): JsonResponse
    {
        $requirement = ResourceRequirement::findOrFail($id);

        return response()->json($requirement);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $requirement = ResourceRequirement::findOrFail($id);

        $validated = $request->validate([
            'resource_type' => 'sometimes|string|max:100',
            'required_capabilities' => 'nullable|array',
            'count' => 'sometimes|integer|min:1',
            'min_capacity' => 'nullable|integer|min:1',
        ]);

        $requirement->update($validated);

        return response()->json($requirement);
    }

    public function destroy(string $id): JsonResponse
    {
        $requirement = ResourceRequirement::findOrFail($id);
        $requirement->delete();

        return response()->json(null, 204);
    }
}
