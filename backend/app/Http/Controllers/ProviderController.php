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
    protected \App\Services\NotificationService $notificationService;

    public function __construct(MediaService $mediaService, \App\Services\NotificationService $notificationService)
    {
        $this->mediaService = $mediaService;
        $this->notificationService = $notificationService;
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
        $query->with(['specialists']);

        $providers = $query->paginate(20);

        return response()->json($providers);
    }

    /**
     * Display the specified provider with full profile details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        // Check if $id is a valid UUID format
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);

        $query = Provider::with([
            'specialists' => function ($query) {
                // Use ONLY the assignments relationship (canonical domain model)
                $query->where('active', true)
                      ->whereHas('assignments', function ($assignmentQuery) {
                          $assignmentQuery->where('status', 'active')
                                         ->orWhere('status', 'ACTIVE');
                      });
            },
            'services' => function ($query) {
                $query->where('active', true);
            },
            'reviews' => function ($query) {
                $query->latest()->limit(10);
            }
        ]);

        if ($isUuid) {
            $query->where('id', $id);
        } else {
            $query->where('slug', $id);
        }

        $provider = $query->first();

        if (!$provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        // Notify provider owner if no services are configured
        // Only trigger this on the main provider show endpoint, not on sub-resource endpoints
        try {
            if ($provider->relationLoaded('services') && (empty($provider->services) || count($provider->services) === 0)) {
                // Get provider owner contact info
                $ownerEmail = $provider->email;
                $ownerPhone = $provider->phone;
                
                // Only notify if we have contact information and provider is active
                if (($ownerEmail || $ownerPhone) && $provider->active) {
                    // Use a cache key to avoid spamming notifications (notify once per day)
                    $cacheKey = "provider_no_services_notification_{$provider->id}";
                    if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                        $this->notificationService->notifyProviderMissingServices(
                            $provider->id,
                            $provider->display_name,
                            $ownerEmail,
                            $ownerPhone
                        );
                        // Cache for 24 hours
                        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addHours(24));
                    }
                }
            }
        } catch (\Exception $e) {
            // Don't let notification errors break the provider response
            \Log::warning('Failed to send missing services notification', [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
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
        $firstAvailable = $availabilityEngine->getFirstAvailableSlot($provider->id, $provider->location_id);

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
        ]);
    }

    /**
     * Get operationally assignable specialists for a provider
     * Returns only specialists with active SpecialistAssignment for this provider
     */
    public function assignableSpecialists(Request $request, string $provider): JsonResponse
    {
        // Check if $provider is a valid UUID format
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $provider);

        $query = Provider::query();

        if ($isUuid) {
            $query->where('id', $provider);
        } else {
            $query->where('slug', $provider);
        }

        $providerRecord = $query->first();

        if (!$providerRecord) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        // Get optional location_id from request
        $locationId = $request->query('location_id');

        // Query specialists with active assignments for this provider
        // Use ONLY the assignments relationship (canonical domain model)
        // Specialists work for providers via SpecialistAssignment, not direct ownership
        $specialists = \App\Models\Specialist::where('active', true)
            ->whereHas('assignments', function ($assignmentQuery) use ($providerRecord, $locationId) {
                $assignmentQuery->where('provider_id', $providerRecord->id)
                               ->whereIn('status', ['active', 'ACTIVE']);

                // Canonical semantics:
                // - If location_id is specified: only match exact location_id (exclude NULL)
                // - If location_id is NULL (provider-wide): match both location-specific and NULL assignments
                if ($locationId) {
                    $assignmentQuery->where('location_id', $locationId);
                }
            })
            ->get();

        return response()->json($specialists);
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
        \Log::info('ProviderController::update called', [
            'provider_id' => $id,
            'content_type' => $request->header('Content-Type'),
            'has_cover_image_file' => $request->hasFile('cover_image'),
            'all_files' => array_keys($request->allFiles()),
            'all_data' => array_keys($request->all()),
            'all_input' => $request->all(),
            'request_method' => $request->method(),
        ]);

        $provider = Provider::findOrFail($id);

        try {
            $validated = $request->validate([
                'type' => 'sometimes|in:salon,independent_specialist,spa,beauty_school,mobile_team',
                'status' => 'sometimes|in:pending,active,suspended,inactive',
                'display_name' => 'sometimes|string|max:255',
                'slug' => 'sometimes|string|max:255|unique:providers,slug,' . $id,
                'description' => 'sometimes|string',
                'logo' => 'sometimes|image|max:10240',
                'cover_image' => 'sometimes|image|max:10240',
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
            \Log::info('Validation passed', ['validated' => array_keys($validated)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', ['errors' => $e->errors()]);
            throw $e;
        }

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
            \Log::info('Processing cover image upload');
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
            \Log::info('Cover image uploaded', ['media_id' => $media->id]);
        }

        $provider->update($data);

        \Log::info('Provider updated', [
            'provider_id' => $provider->id,
            'cover_image' => $provider->cover_image,
        ]);

        // Load media relationships for response
        $provider->load('coverImageMedia', 'logoMedia');

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

    /**
     * Check if a slug is available for a new provider
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|min:3|max:255',
        ]);

        $slug = $request->input('slug');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $slug));

        $exists = Provider::where('slug', $slug)->exists();

        if ($exists) {
            // Generate suggestions
            $suggestions = [];
            for ($i = 1; $i <= 3; $i++) {
                $suggestion = $slug . '-' . $i;
                if (!Provider::where('slug', $suggestion)->exists()) {
                    $suggestions[] = $suggestion;
                }
            }

            return response()->json([
                'available' => false,
                'suggestions' => $suggestions,
            ]);
        }

        return response()->json([
            'available' => true,
        ]);
    }
}
