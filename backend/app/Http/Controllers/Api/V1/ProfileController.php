<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = Profile::with('provider')->get();
        return response()->json($profiles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:profiles',
            'name' => 'nullable|string',
            'role' => 'sometimes|in:owner,staff,admin',
            'location_id' => 'required|uuid|exists:locations,id',
        ]);

        $profile = Profile::create($validated);
        return response()->json($profile->load('location'), 201);
    }

    public function show(Profile $profile): JsonResponse
    {
        return response()->json($profile->load('location'));
    }

    public function update(Request $request, Profile $profile): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'sometimes|email|unique:profiles,email,' . $profile->id,
            'name' => 'nullable|string',
            'role' => 'sometimes|in:owner,staff,admin',
            'location_id' => 'sometimes|uuid|exists:locations,id',
        ]);

        $profile->update($validated);
        return response()->json($profile->load('location'));
    }

    public function destroy(Profile $profile): JsonResponse
    {
        $profile->delete();
        return response()->json(null, 204);
    }

    public function byLocation(string $location): JsonResponse
    {
        $profiles = Profile::where('location_id', $location)->get();
        return response()->json($profiles);
    }
}
