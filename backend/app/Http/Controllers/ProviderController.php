<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProviderController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Display a listing of providers with search and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Provider::query();

        // Search by display name or slug
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Load relationships based on type
        $query->with(['salon', 'specialists']);

        $providers = $query->paginate(20);

        return response()->json($providers);
    }

    /**
     * Display the specified provider with full profile details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $provider = Provider::with([
            'salon',
            'specialists' => function ($query) {
                $query->where('is_active', true);
            },
            'services' => function ($query) {
                $query->where('active', true);
            },
            'reviews' => function ($query) {
                $query->latest()->limit(10);
            }
        ])
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        // Get operating hours
        $operatingHours = [];
        for ($day = 0; $day <= 6; $day++) {
            $schedule = \App\Models\ProviderSchedule::where('provider_id', $provider->id)
                ->where('day_of_week', $day)
                ->where('effective_date', '<=', now()->toDateString())
                ->where(function ($query) {
                    $query->whereNull('expires_date')
                        ->orWhere('expires_date', '>=', now()->toDateString());
                })
                ->orderByDesc('effective_date')
                ->first();

            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $operatingHours[] = [
                'day' => $dayNames[$day],
                'day_of_week' => $day,
                'open' => $schedule && !$schedule->is_closed ? $schedule->open_time : null,
                'close' => $schedule && !$schedule->is_closed ? $schedule->close_time : null,
                'is_closed' => $schedule ? $schedule->is_closed : true,
            ];
        }

        // Get first available slot
        $availabilityEngine = new \App\Services\AvailabilityEngine();
        $firstAvailable = $availabilityEngine->getFirstAvailableSlot($provider->id);

        return response()->json([
            'id' => $provider->id,
            'type' => $provider->type,
            'status' => $provider->status,
            'display_name' => $provider->display_name,
            'slug' => $provider->slug,
            'description' => $provider->description,
            'logo' => $provider->logo_url,
            'cover_image' => $provider->cover_image_url,
            'phone' => $provider->phone,
            'email' => $provider->email,
            'website' => $provider->website,
            'location' => $provider->location,
            'social_links' => $provider->social_links,
            'timezone' => $provider->timezone,
            'rating' => $provider->rating,
            'review_count' => $provider->review_count,
            'active' => $provider->active,
            'operating_hours' => $operatingHours,
            'first_available_slot' => $firstAvailable,
            'specialists' => $provider->specialists,
            'services' => $provider->services,
            'reviews' => $provider->reviews,
            'salon' => $provider->salon,
        ]);
    }

    /**
     * Store a newly created provider
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:salon,independent_specialist,spa,beauty_school,mobile_team',
            'display_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:providers,slug',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'cover_image' => 'nullable|image|max:2048',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'location' => 'nullable|array',
            'social_links' => 'nullable|array',
            'settings' => 'nullable|array',
            'active' => 'boolean',
        ]);

        $data = $validated;

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $media = $this->mediaService->upload($request->file('logo'), [
                'directory' => 'providers/logos',
                'alt_text' => $data['display_name'] . ' logo',
            ]);
            $data['logo'] = $media->id;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            $media = $this->mediaService->upload($request->file('cover_image'), [
                'directory' => 'providers/covers',
                'alt_text' => $data['display_name'] . ' cover image',
            ]);
            $data['cover_image'] = $media->id;
        }

        $provider = Provider::create($validated);

        return response()->json($provider, 201);
    }

    /**
     * Update the specified provider
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|in:salon,independent_specialist,spa,beauty_school,mobile_team',
            'status' => 'sometimes|in:pending,active,suspended,inactive',
            'display_name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:providers,slug,' . $id,
            'description' => 'sometimes|string',
            'logo' => 'sometimes|image|max:2048',
            'cover_image' => 'sometimes|image|max:2048',
            'phone' => 'sometimes|string',
            'email' => 'sometimes|email',
            'website' => 'sometimes|url',
            'location' => 'sometimes|array',
            'social_links' => 'sometimes|array',
            'settings' => 'sometimes|array',
            'rating' => 'sometimes|numeric|min:0|max:5',
            'review_count' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);

        $data = $validated;

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old media if exists
            if ($provider->logo && \Illuminate\Support\Str::isUuid($provider->logo)) {
                $oldMedia = \App\Models\Media::find($provider->logo);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            }
            
            $media = $this->mediaService->upload($request->file('logo'), [
                'directory' => 'providers/logos',
                'alt_text' => $data['display_name'] ?? $provider->display_name . ' logo',
            ]);
            $data['logo'] = $media->id;
        }

        // Handle cover image upload
        if ($request->hasFile('cover_image')) {
            // Delete old media if exists
            if ($provider->cover_image && \Illuminate\Support\Str::isUuid($provider->cover_image)) {
                $oldMedia = \App\Models\Media::find($provider->cover_image);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            }
            
            $media = $this->mediaService->upload($request->file('cover_image'), [
                'directory' => 'providers/covers',
                'alt_text' => $data['display_name'] ?? $provider->display_name . ' cover image',
            ]);
            $data['cover_image'] = $media->id;
        }

        $provider->update($data);

        return response()->json($provider);
    }

    /**
     * Remove the specified provider
     */
    public function destroy(string $id): JsonResponse
    {
        $provider = Provider::findOrFail($id);
        $provider->delete();

        return response()->json(['message' => 'Provider deleted successfully']);
    }
}
