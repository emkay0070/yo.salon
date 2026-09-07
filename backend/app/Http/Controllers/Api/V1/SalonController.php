<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Salon;
use App\Services\BranchServiceCatalogResolver;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalonController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function index(): JsonResponse
    {
        $salons = Salon::all();
        return response()->json($salons);
    }

    public function checkSlug(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string',
        ]);

        $slug = $validated['slug'];
        $exists = Salon::where('slug', $slug)->exists();

        if (!$exists) {
            return response()->json([
                'available' => true,
                'slug' => $slug,
            ]);
        }

        // Generate suggestions
        $suggestions = [];
        for ($i = 1; $i <= 5; $i++) {
            $suggestion = $slug . '-' . $i;
            if (!Salon::where('slug', $suggestion)->exists()) {
                $suggestions[] = $suggestion;
            }
        }

        return response()->json([
            'available' => false,
            'slug' => $slug,
            'suggestions' => $suggestions,
        ]);
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
        $salon->load(['provider', 'services' => function ($query) {
            $query->where('active', true);
        }, 'staff' => function ($query) {
            $query->where('active', true);
        }]);

        // Get reviews for this salon
        $reviews = \DB::table('reviews')
            ->where('reviewable_type', 'App\\Models\\Salon')
            ->where('reviewable_id', $salon->id)
            ->where('is_approved', true)
            ->select('id', 'rating', 'comment', 'customer_name', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'author' => $review->customer_name,
                    'date' => $review->created_at,
                ];
            });

        // Get booking count
        $bookingCount = \DB::table('bookings')
            ->where('salon_id', $salon->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        return response()->json([
            'id' => $salon->id,
            'provider_id' => $salon->provider_id,
            'name' => $salon->name,
            'slug' => $salon->slug,
            'description' => $salon->description,
            'logo' => $salon->logo,
            'logo_url' => $salon->logo_url,
            'cover_image' => $salon->cover_image,
            'cover_image_url' => $salon->cover_image_url,
            'address' => $salon->address,
            'phone' => $salon->phone,
            'email' => $salon->email,
            'whatsapp' => $salon->whatsapp,
            'city' => $salon->city,
            'opening_hours' => $salon->opening_hours,
            'rating' => $salon->rating ?? 0,
            'review_count' => $reviews->count(),
            'total_bookings' => $bookingCount,
            'is_open' => $salon->is_open ?? true,
            'parking_info' => $salon->parking_info,
            'provider' => $salon->provider ? [
                'id' => $salon->provider->id,
                'name' => $salon->provider->name,
                'display_name' => $salon->provider->display_name,
                'slug' => $salon->provider->slug,
                'logo' => $salon->provider->logo,
            ] : null,
            'services' => $salon->services,
            'staff' => $salon->staff,
            'reviews' => $reviews,
        ]);
    }

    public function showBySlug(string $identifier): JsonResponse
    {
        $salon = Salon::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        try {
            $salon->load(['services', 'staff', 'provider']);
        } catch (\Exception $e) {
            // If relationships fail, return salon without them
        }

        return response()->json($salon);
    }

    public function showById(string $id): JsonResponse
    {
        $salon = Salon::find($id);
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        return response()->json($salon);
    }

    public function services(string $slug, BranchServiceCatalogResolver $catalogResolver): JsonResponse
    {
        $salon = Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }
        
        $services = $catalogResolver->queryAvailableServices($salon)
            ->where('services.active', true)
            ->get();
            
        return response()->json($services);
    }

    public function specialists(string $slug): JsonResponse
    {
        $salon = Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        // Retrieve from the new Specialists architecture
        $specialists = \App\Models\Specialist::where('provider_id', $salon->provider_id)
            ->where('active', true)
            ->get();

        // Fallback to legacy staff if no specialists exist yet for this provider
        if ($specialists->isEmpty()) {
            $specialists = $salon->staff()->where('staff.active', true)->get();
        }

        return response()->json($specialists);
    }

    public function staff(string $slug): JsonResponse
    {
        $salon = Salon::where('slug', $slug)->first();
        if (!$salon) {
            return response()->json(['message' => 'Salon not found'], 404);
        }

        // Retrieve from the new Specialists architecture
        $specialists = \App\Models\Specialist::where('provider_id', $salon->provider_id)
            ->where('active', true)
            ->get();

        // Fallback to legacy staff if no specialists exist yet for this provider
        if ($specialists->isEmpty()) {
            $specialists = $salon->staff()->where('staff.active', true)->get();
        }

        return response()->json($specialists);
    }

    public function update(Request $request, Salon $salon): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'slug' => 'sometimes|string|unique:salons,slug,' . $salon->id,
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'logo_url' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'country' => 'nullable|string',
            'website' => 'nullable|string',
            'opening_hours' => 'nullable|array',
            'booking_deposit_enabled' => 'sometimes|boolean',
            'deposit_type' => 'sometimes|in:percentage,fixed',
            'deposit_value' => 'sometimes|numeric',
            'deposit_required_for' => 'sometimes|in:all,expensive,weekend',
            'deposit_min_service_amount' => 'sometimes|numeric',
        ]);

        $salon->update($validated);
        return response()->json($salon);
    }

    /**
     * Upload logo for a salon
     */
    public function uploadLogo(Request $request, Salon $salon): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,svg|max:5120', // Max 5MB
        ]);

        $user = auth()->user();

        // Upload the media
        $media = $this->mediaService->upload($request->file('file'), [
            'directory' => 'salon-logos',
            'salon_id' => $salon->id,
            'uploader' => $user,
            'attachable' => $salon,
            'visibility' => Media::VISIBILITY_PUBLIC,
            'alt_text' => 'salon logo',
        ]);

        // Update salon's logo_url
        $salon->update(['logo_url' => $media->url]);

        return response()->json($salon);
    }

    /**
     * Remove logo from a salon
     */
    public function removeLogo(Request $request, Salon $salon): JsonResponse
    {
        // Clear the logo_url
        $salon->update(['logo_url' => null]);

        return response()->json($salon);
    }

    public function destroy(Salon $salon): JsonResponse
    {
        $salon->delete();
        return response()->json(null, 204);
    }
}
