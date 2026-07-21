<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalonController extends Controller
{
    public function index(): JsonResponse
    {
        $salons = Salon::all();
        return response()->json($salons);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'slug' => 'required|string|unique:salons',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'opening_hours' => 'nullable|array',
        ]);

        $salon = Salon::create($validated);
        return response()->json($salon, 201);
    }

    public function show(Salon $salon): JsonResponse
    {
        return response()->json($salon->load(['services', 'staff', 'profiles']));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        $salon = Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }
        return response()->json($salon->load(['services', 'staff']));
    }

    public function services(string $slug): JsonResponse
    {
        $salon = Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }
        return response()->json($salon->services()->where('active', true)->get());
    }

    public function staff(string $slug): JsonResponse
    {
        $salon = Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }
        return response()->json($salon->staff()->where('active', true)->get());
    }

    public function update(Request $request, Salon $salon): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'slug' => 'sometimes|string|unique:salons,slug,' . $salon->id,
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'opening_hours' => 'nullable|array',
        ]);

        $salon->update($validated);
        return response()->json($salon);
    }

    public function destroy(Salon $salon): JsonResponse
    {
        $salon->delete();
        return response()->json(null, 204);
    }

    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|max:100',
        ]);

        $slug = \Illuminate\Support\Str::slug($request->input('slug'));

        $exists = Salon::where('slug', $slug)->exists();

        $suggestions = [];
        if ($exists) {
            // Generate up to 3 numeric suffix suggestions
            for ($i = 2; $i <= 4; $i++) {
                $candidate = $slug . '-' . $i;
                if (!Salon::where('slug', $candidate)->exists()) {
                    $suggestions[] = $candidate;
                }
            }
        }

        return response()->json([
            'available'   => !$exists,
            'slug'        => $slug,
            'suggestions' => $suggestions,
        ]);
    }
}
