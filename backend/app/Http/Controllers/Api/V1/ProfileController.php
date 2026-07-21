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
        $profiles = Profile::with('salon')->get();
        return response()->json($profiles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:profiles',
            'name' => 'nullable|string',
            'role' => 'sometimes|in:owner,staff,admin',
            'salon_id' => 'required|uuid|exists:salons,id',
        ]);

        $profile = Profile::create($validated);
        return response()->json($profile->load('salon'), 201);
    }

    public function show(Profile $profile): JsonResponse
    {
        return response()->json($profile->load('salon'));
    }

    public function update(Request $request, Profile $profile): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'sometimes|email|unique:profiles,email,' . $profile->id,
            'name' => 'nullable|string',
            'role' => 'sometimes|in:owner,staff,admin',
            'salon_id' => 'sometimes|uuid|exists:salons,id',
        ]);

        $profile->update($validated);
        return response()->json($profile->load('salon'));
    }

    public function destroy(Profile $profile): JsonResponse
    {
        $profile->delete();
        return response()->json(null, 204);
    }

    public function bySalon(string $salon): JsonResponse
    {
        $profiles = Profile::where('salon_id', $salon)->get();
        return response()->json($profiles);
    }
}
