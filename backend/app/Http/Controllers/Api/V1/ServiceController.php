<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function index(Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) return response()->json(['message' => 'No salon associated with your account'], 403);
        
        // Services are provider-scoped, filter by salon's provider
        $query = Service::with('provider')->where('provider_id', $salon->provider_id);
        $services = $query->get();
        return response()->json($services);
    }

    public function store(Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) return response()->json(['message' => 'No salon associated with your account'], 403);

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'duration' => 'required|integer',
            'category' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $validated;
        $data['provider_id'] = $salon->provider_id;

        if ($request->hasFile('image')) {
            $media = $this->mediaService->upload($request->file('image'), [
                'directory' => 'services',
                'provider_id' => $salon->provider_id,
                'salon_id' => $salon->id,
                'alt_text' => $data['name'] . ' image',
            ]);
            $data['image_media_id'] = $media->id;
        }

        $service = Service::create($data);
        
        // Attach to the current salon automatically
        \Illuminate\Support\Facades\DB::table('salon_service')->insert([
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'is_available' => true,
        ]);

        return response()->json($service->load('provider'), 201);
    }

    public function show(Service $service): JsonResponse
    {
        $service->load(['provider']);

        // Get available specialists for this service
        $availableSpecialists = \DB::table('specialists')
            ->join('specialist_service', 'specialists.id', '=', 'specialist_service.specialist_id')
            ->where('specialist_service.service_id', $service->id)
            ->where('specialists.active', true)
            ->select(
                'specialists.id',
                'specialists.name',
                'specialists.specialties',
                'specialists.photo',
                'specialists.rating',
                'specialists.review_count',
                'specialist_service.skill_level',
                'specialist_service.price_override'
            )
            ->get();

        // Get salons that offer this service (via provider)
        $salons = \DB::table('salons')
            ->where('provider_id', $service->provider_id)
            ->select(
                'id',
                'name',
                'slug',
                'logo',
                'address',
                'phone'
            )
            ->get();

        // Get booking count for popularity
        $bookingCount = \DB::table('bookings')
            ->where('service_id', $service->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Get reviews for this service (via bookings)
        $reviews = \DB::table('reviews')
            ->join('bookings', 'reviews.booking_id', '=', 'bookings.id')
            ->join('customers', 'reviews.customer_id', '=', 'customers.id')
            ->where('bookings.service_id', $service->id)
            ->select('reviews.id', 'reviews.rating', 'reviews.comment', 'customers.name as customer_name', 'reviews.created_at')
            ->orderBy('reviews.created_at', 'desc')
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

        return response()->json([
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'preparation' => $service->preparation,
            'aftercare' => $service->aftercare,
            'cancellation_policy' => $service->cancellation_policy,
            'price' => $service->price,
            'duration' => $service->duration,
            'buffer_before' => $service->buffer_before,
            'buffer_after' => $service->buffer_after,
            'requires_specialist' => $service->requires_specialist,
            'max_concurrent' => $service->max_concurrent,
            'min_booking_notice' => $service->min_booking_notice,
            'max_booking_days_ahead' => $service->max_booking_days_ahead,
            'cancellation_cutoff_hours' => $service->cancellation_cutoff_hours,
            'category' => $service->category,
            'image_path' => $service->image_path,
            'image_url' => $service->image_url,
            'images' => $service->images,
            'active' => $service->active,
            'provider' => $service->provider ? [
                'id' => $service->provider->id,
                'name' => $service->provider->name,
                'display_name' => $service->provider->display_name,
                'slug' => $service->provider->slug,
                'logo' => $service->provider->logo,
                'description' => $service->provider->description,
            ] : null,
            'available_specialists' => $availableSpecialists,
            'salons' => $salons,
            'popularity' => $bookingCount,
            'rating' => $service->rating ?? 0,
            'review_count' => $reviews->count(),
            'reviews' => $reviews,
            'what_included' => $service->what_included ?? [],
            'suitable_for' => $service->suitable_for ?? [],
        ]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'duration' => 'sometimes|integer',
            'category' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $validated;
        
        if ($request->hasFile('image')) {
            // Delete old media if exists
            if ($service->image_media_id) {
                $oldMedia = \App\Models\Media::find($service->image_media_id);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            }
            
            $media = $this->mediaService->upload($request->file('image'), [
                'directory' => 'services',
                'provider_id' => $service->provider_id,
                'salon_id' => auth()->user()->currentSalon()?->id,
                'alt_text' => $data['name'] ?? $service->name . ' image',
            ]);
            $data['image_media_id'] = $media->id;
        }

        $service->update($data);
        return response()->json($service->load('provider'));
    }

    public function destroy(Service $service): JsonResponse
    {
        // Delete media if exists
        if ($service->image_media_id) {
            $media = \App\Models\Media::find($service->image_media_id);
            if ($media) {
                $this->mediaService->delete($media);
            }
        }
        // Also delete old image_path if exists (for backward compatibility)
        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }
        $service->delete();
        return response()->json(null, 204);
    }

    public function bySalon(string $salon): JsonResponse
    {
        $services = Service::where('salon_id', $salon)
            ->where('active', true)
            ->get();
        return response()->json($services);
    }

    public function indexForPortal(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        if (!$providerId) {
            return response()->json(['message' => 'No provider context found'], 400);
        }

        $services = Service::where('provider_id', $providerId)
            ->where('active', true)
            ->get();
            
        return response()->json($services);
    }
}
