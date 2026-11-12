<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Location;
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
        $provider = auth()->user()->currentProvider();
        if (!$provider) return response()->json(['message' => 'No provider associated with your account'], 403);

        // Services are provider-scoped
        $query    = Service::with('provider')->where('provider_id', $provider->id);
        $services = $query->get();
        return response()->json($services);
    }

    public function store(Request $request): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) return response()->json(['message' => 'No provider associated with your account'], 403);

        $validated = $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'price'       => 'required|numeric',
            'duration'    => 'required|integer',
            'category'    => 'nullable|string',
            'active'      => 'sometimes|boolean',
            'image'       => 'nullable|image|max:2048',
        ]);

        $data                = $validated;
        $data['provider_id'] = $provider->id;

        if ($request->hasFile('image')) {
            $media                  = $this->mediaService->upload($request->file('image'), [
                'directory'   => 'services',
                'provider_id' => $provider->id,
                'alt_text'    => $data['name'] . ' image',
            ]);
            $data['image_media_id'] = $media->id;
        }

        $service = Service::create($data);

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

        // Get locations that offer this service (via provider)
        $locations = Location::where('provider_id', $service->provider_id)
            ->where('is_active', true)
            ->get()
            ->map(function ($location) {
                return [
                    'id'      => $location->id,
                    'name'    => $location->name,
                    'slug'    => $location->slug,
                    'logo'    => $location->provider?->logo,
                    'address' => $location->address,
                    'phone'   => $location->phone,
                ];
            });

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
                    'id'      => $review->id,
                    'rating'  => $review->rating,
                    'comment' => $review->comment,
                    'author'  => $review->customer_name,
                    'date'    => $review->created_at,
                ];
            });

        return response()->json([
            'id'                        => $service->id,
            'name'                      => $service->name,
            'description'               => $service->description,
            'preparation'               => $service->preparation,
            'aftercare'                 => $service->aftercare,
            'cancellation_policy'       => $service->cancellation_policy,
            'price'                     => $service->price,
            'duration'                  => $service->duration,
            'buffer_before'             => $service->buffer_before,
            'buffer_after'              => $service->buffer_after,
            'requires_specialist'       => $service->requires_specialist,
            'max_concurrent'            => $service->max_concurrent,
            'min_booking_notice'        => $service->min_booking_notice,
            'max_booking_days_ahead'    => $service->max_booking_days_ahead,
            'cancellation_cutoff_hours' => $service->cancellation_cutoff_hours,
            'category'                  => $service->category,
            'image_path'                => $service->image_path,
            'image_url'                 => $service->image_url,
            'images'                    => $service->images,
            'active'                    => $service->active,
            'provider'                  => $service->provider ? [
                'id'           => $service->provider->id,
                'display_name' => $service->provider->display_name,
                'slug'         => $service->provider->slug,
                'logo'         => $service->provider->logo,
                'description'  => $service->provider->description,
            ] : null,
            'available_specialists'     => $availableSpecialists,
            'locations'                 => $locations,
            'popularity'                => $bookingCount,
            'rating'                    => $service->rating ?? 0,
            'review_count'              => $reviews->count(),
            'reviews'                   => $reviews,
            'what_included'             => $service->what_included ?? [],
            'suitable_for'              => $service->suitable_for ?? [],
        ]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string',
            'description' => 'nullable|string',
            'price'       => 'sometimes|numeric',
            'duration'    => 'sometimes|integer',
            'category'    => 'nullable|string',
            'active'      => 'sometimes|boolean',
            'image'       => 'nullable|image|max:2048',
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

            $media                  = $this->mediaService->upload($request->file('image'), [
                'directory'   => 'services',
                'provider_id' => $service->provider_id,
                'alt_text'    => ($data['name'] ?? $service->name) . ' image',
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

    /**
     * Get services by provider.
     */
    public function byProvider(string $providerId): JsonResponse
    {
        $provider = \App\Models\Provider::find($providerId);
        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        $services = Service::where('provider_id', $provider->id)
            ->where('active', true)
            ->get();

        return response()->json($services);
    }

    /**
     * Get services by location (branch).
     */
    public function byLocation(string $locationId): JsonResponse
    {
        $location = Location::find($locationId);
        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $services = Service::where('provider_id', $location->provider_id)
            ->where('active', true)
            ->get();

        return response()->json($services);
    }

    public function indexForPortal(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $locationId = $request->attributes->get('location_id');

        if ($providerId) {
            // Resolve the primary location for supplementary context
            $location = $locationId
                ? Location::find($locationId)
                : Location::where('provider_id', $providerId)->where('is_primary', true)->first();

            $services = Service::where('provider_id', $providerId)
                ->where('active', true)
                ->get()
                ->map(function (Service $service) use ($location) {
                    $row = $service->toArray();
                    if ($location) {
                        $row['location_id'] = $location->id;
                        $row['location']    = [
                            'id'   => $location->id,
                            'name' => $location->name,
                            'slug' => $location->slug,
                            'city' => $location->city,
                        ];
                    }
                    return $row;
                });

            return response()->json($services);
        }

        // No context at all
        return response()->json([]);
    }
}
