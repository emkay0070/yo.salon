<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestimonialController extends Controller
{
    /**
     * Get testimonials for a provider
     */
    public function index(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        
        if (!$providerId) {
            return response()->json(['error' => 'Provider context not found'], 400);
        }

        $testimonials = Testimonial::forProvider($providerId)
            ->publiclyVisible()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($testimonials);
    }

    /**
     * Get public testimonials for a provider (no auth required)
     */
    public function publicIndex(Request $request, string $slug): JsonResponse
    {
        $provider = \App\Models\Provider::where('slug', $slug)->first();
        
        if (!$provider) {
            return response()->json(['error' => 'Provider not found'], 404);
        }

        $testimonials = Testimonial::forProvider($provider->id)
            ->publiclyVisible()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($testimonials);
    }

    /**
     * Store a new testimonial
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string',
            'visible' => 'sometimes|boolean',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'customer_id' => 'nullable|uuid|exists:customers,id',
        ]);

        $providerId = $request->attributes->get('provider_id');
        
        if (!$providerId) {
            return response()->json(['error' => 'Provider context not found'], 400);
        }

        $testimonial = Testimonial::create([
            'provider_id' => $providerId,
            'location_id' => $validated['location_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'rating' => $validated['rating'],
            'content' => $validated['content'],
            'visible' => $validated['visible'] ?? false,
            'consent_given' => false,
            'status' => 'pending',
        ]);

        return response()->json($testimonial, 201);
    }

    /**
     * Show a specific testimonial
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        
        if (!$providerId) {
            return response()->json(['error' => 'Provider context not found'], 400);
        }

        $testimonial = Testimonial::where('id', $id)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        return response()->json($testimonial);
    }

    /**
     * Update a testimonial
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'customer_email' => 'nullable|email',
            'rating' => 'sometimes|integer|min:1|max:5',
            'content' => 'sometimes|string',
            'visible' => 'sometimes|boolean',
            'consent_given' => 'sometimes|boolean',
            'status' => 'sometimes|in:pending,approved,rejected,flagged',
        ]);

        $providerId = $request->attributes->get('provider_id');
        
        if (!$providerId) {
            return response()->json(['error' => 'Provider context not found'], 400);
        }

        $testimonial = Testimonial::where('id', $id)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        // Enforce consent: cannot set visible=true without consent_given=true
        if (isset($validated['visible']) && $validated['visible'] === true) {
            if (!$testimonial->consent_given && !(isset($validated['consent_given']) && $validated['consent_given'] === true)) {
                return response()->json(['error' => 'Cannot publish testimonial without consent'], 422);
            }
        }

        // If consent is being given, record the date
        if (isset($validated['consent_given']) && $validated['consent_given'] === true && !$testimonial->consent_given) {
            $validated['consent_date'] = now();
        }

        $testimonial->update($validated);

        return response()->json($testimonial);
    }

    /**
     * Delete a testimonial
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        
        if (!$providerId) {
            return response()->json(['error' => 'Provider context not found'], 400);
        }

        $testimonial = Testimonial::where('id', $id)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        $testimonial->delete();

        return response()->json(null, 204);
    }
}
