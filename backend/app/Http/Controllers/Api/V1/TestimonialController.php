<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestimonialController extends Controller
{
    /**
     * Get testimonials for a salon
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $testimonials = Testimonial::where('salon_id', $salonId)
            ->where('visible', true)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($testimonials);
    }

    /**
     * Get public testimonials for a salon (no auth required)
     */
    public function publicIndex(Request $request, string $slug): JsonResponse
    {
        $salon = \App\Models\Salon::where('slug', $slug)->first();
        
        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }

        $testimonials = Testimonial::where('salon_id', $salon->id)
            ->where('visible', true)
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
        ]);

        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $testimonial = Testimonial::create([
            'salon_id' => $salonId,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'rating' => $validated['rating'],
            'content' => $validated['content'],
            'visible' => $validated['visible'] ?? false,
        ]);

        return response()->json($testimonial, 201);
    }

    /**
     * Show a specific testimonial
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $testimonial = Testimonial::where('id', $id)
            ->where('salon_id', $salonId)
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
        ]);

        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $testimonial = Testimonial::where('id', $id)
            ->where('salon_id', $salonId)
            ->firstOrFail();

        $testimonial->update($validated);

        return response()->json($testimonial);
    }

    /**
     * Delete a testimonial
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $testimonial = Testimonial::where('id', $id)
            ->where('salon_id', $salonId)
            ->firstOrFail();

        $testimonial->delete();

        return response()->json(null, 204);
    }
}
