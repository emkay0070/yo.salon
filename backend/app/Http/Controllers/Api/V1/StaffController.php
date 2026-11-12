<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\MediaService;
use App\Services\CapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    protected MediaService $mediaService;
    protected CapabilityResolver $capabilityResolver;

    public function __construct(MediaService $mediaService, CapabilityResolver $capabilityResolver)
    {
        $this->mediaService = $mediaService;
        $this->capabilityResolver = $capabilityResolver;
    }

    public function index(Request $request): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) return response()->json(['message' => 'No provider associated with your account'], 403);

        $providerUser = \App\Models\ProviderUser::where('user_id', auth()->id())
            ->where('provider_id', $provider->id)
            ->first();

        if (!$providerUser) {
            return response()->json(['message' => 'No provider user association found'], 403);
        }

        $locationId = $request->attributes->get('location_id');
        $locationScope = $request->attributes->get('location_scope', 'all');

        $query = Staff::with('provider', 'location', 'user')
            ->where('provider_id', $provider->id);

        // If location_scope is 'specific' and location_id is provided, filter by that location
        // This applies to both provider_wide and location_scoped users when they explicitly request a specific location
        if ($locationScope === 'specific' && $locationId) {
            // Validate user has access to this location
            if (!$providerUser->canAccessLocation($locationId)) {
                return response()->json(['message' => 'You do not have access to this location'], 403);
            }
            $query->where('location_id', $locationId);
        }
        // Provider-wide users with no specific location request see all staff
        elseif ($providerUser->isProviderWide()) {
            // No location filtering - provider-wide access
        }
        // Location-scoped users only see staff in their permitted locations
        elseif ($providerUser->isLocationScoped()) {
            if ($locationId) {
                // Validate user has access to this location
                if (!$providerUser->canAccessLocation($locationId)) {
                    return response()->json(['message' => 'You do not have access to this location'], 403);
                }
                $query->where('location_id', $locationId);
            } else {
                // No location specified - show staff across all permitted locations
                $permittedLocationIds = $providerUser->getPermittedLocations()->pluck('id');
                $query->whereIn('location_id', $permittedLocationIds);
            }
        }

        $staff = $query->get();

        // Filter to only staff with active specialist assignments (bookable specialists)
        // This matches the public-facing booking flow behavior
        // Uses two-pass lookup: direct staff_id match + name fallback for legacy data
        $staffWithAssignments = $staff->filter(function ($member) use ($provider) {
            // Pass 1: direct staff_id match (canonical path)
            $assignment = \App\Models\SpecialistAssignment::where('provider_id', $provider->id)
                ->whereIn('status', ['active', 'ACTIVE'])
                ->where('staff_id', $member->id)
                ->first();

            // Pass 2: name-based fallback for legacy assignments where staff_id was never backfilled
            if (!$assignment) {
                $assignment = \App\Models\SpecialistAssignment::where('provider_id', $provider->id)
                    ->whereIn('status', ['active', 'ACTIVE'])
                    ->whereNull('staff_id')
                    ->whereHas('specialist', fn($q) => $q->where('name', $member->name))
                    ->first();
            }

            return $assignment !== null;
        });

        // Append specialist_id from their active SpecialistAssignment for this provider.
        // This is needed by the Walk-in Modal to correctly set specialist_id on a booking.
        // Staff with no active assignment (receptionists, managers) will have specialist_id = null.
        //
        // Lookup strategy (two-pass for resilience):
        //   1. Direct: match SpecialistAssignment.staff_id = staff.id (canonical, post-migration)
        //   2. Name fallback: join through Specialist.name = staff.name (handles legacy rows with null staff_id)
        $staff = $staffWithAssignments->map(function ($member) use ($provider) {
            // Pass 1: direct staff_id match (canonical path)
            $assignment = \App\Models\SpecialistAssignment::where('provider_id', $provider->id)
                ->whereIn('status', ['active', 'ACTIVE'])
                ->where('staff_id', $member->id)
                ->first();

            // Pass 2: name-based fallback for legacy assignments where staff_id was never backfilled
            if (!$assignment) {
                $assignment = \App\Models\SpecialistAssignment::where('provider_id', $provider->id)
                    ->whereIn('status', ['active', 'ACTIVE'])
                    ->whereNull('staff_id')
                    ->whereHas('specialist', fn($q) => $q->where('name', $member->name))
                    ->first();

                // Opportunistically backfill so we don't hit this path again
                if ($assignment) {
                    $assignment->update(['staff_id' => $member->id]);
                }
            }

            $member->specialist_id = $assignment?->specialist_id;
            return $member;
        });

        return response()->json($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $provider = auth()->user()->currentProvider();
        if (!$provider) return response()->json(['message' => 'No provider associated with your account'], 403);

        // Wrap in transaction to prevent race conditions on capacity checks
        return DB::transaction(function () use ($request, $provider) {
            // Check if provider can add more staff members (service-layer enforcement)
            // This check is inside the transaction to prevent race conditions
            if (!$this->capabilityResolver->canAdd($provider->id, 'STAFF_SEAT')) {
                $limit = $this->capabilityResolver->limit($provider->id, 'STAFF_SEAT');
                $current = $this->capabilityResolver->usage($provider->id, 'STAFF_SEAT');
                
                return response()->json([
                    'message' => 'Staff limit reached',
                    'error' => 'STAFF_LIMIT_EXCEEDED',
                    'limit' => $limit,
                    'current' => $current,
                    'remaining' => $this->capabilityResolver->remaining($provider->id, 'STAFF_SEAT'),
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string',
                'phone' => 'nullable|string',
                'email' => 'nullable|email',
                'specializations' => 'nullable',
                'availability' => 'nullable',
                'photo' => 'nullable|image|max:2048',
                'active' => 'sometimes|boolean',
                'role' => 'nullable|string',
                'location_id' => 'nullable|uuid|exists:locations,id',
            ]);

            $data = $validated;
            // Set canonical Provider ownership
            $data['provider_id'] = $provider->id;
            // Do NOT automatically assign location_id - null is valid provider-level context

            // Set active to true by default if not provided
            if (!isset($data['active'])) {
                $data['active'] = true;
            }

            if ($request->hasFile('photo')) {
                $media = $this->mediaService->upload($request->file('photo'), [
                    'directory' => 'staff/photos',
                    'provider_id' => $provider->id,
                    'alt_text' => $data['name'] . ' photo',
                ]);
                $data['photo'] = $media->id;
            }

            // If specializations is a string, parse it as JSON (since frontend sends it as JSON string)
            if (isset($data['specializations']) && is_string($data['specializations'])) {
                $data['specializations'] = json_decode($data['specializations'], true);
            }

            // If availability is a string, parse it as JSON
            if (isset($data['availability']) && is_string($data['availability'])) {
                $data['availability'] = json_decode($data['availability'], true);
            }

            $staffMember = Staff::create($data);
            return response()->json($staffMember->load(['provider', 'location']), 201);
        });
    }

    public function show(Staff $staff): JsonResponse
    {
        $staff->load(['provider', 'location']);

        // Get services offered by this staff member.
        // Since staff.id ≠ specialist.id in the new architecture,
        // we resolve the Specialist identity via SpecialistAssignment.
        $assignment = \App\Models\SpecialistAssignment::where('staff_id', $staff->id)
            ->where('provider_id', $staff->provider_id)
            ->where('location_id', $staff->location_id)
            ->first();

        $services = collect();
        if ($assignment) {
            $services = \DB::table('services')
                ->join('specialist_service', 'services.id', '=', 'specialist_service.service_id')
                ->where('specialist_service.specialist_id', $assignment->specialist_id)
                ->where('services.active', true)
                ->select(
                    'services.id',
                    'services.name',
                    'services.description',
                    'services.price',
                    'services.duration',
                    'services.category',
                    'services.image_url',
                    'specialist_service.skill_level',
                    'specialist_service.price_override'
                )
                ->get();
        }

        // Get reviews for this staff member
        $reviews = \DB::table('reviews')
            ->where('reviewable_type', 'App\\Models\\Staff')
            ->where('reviewable_id', $staff->id)
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
            ->where('staff_id', $staff->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        return response()->json([
            'id' => $staff->id,
            'user_id' => $staff->user_id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'photo' => $staff->photo,
            'photo_url' => $staff->photo_url,
            'active' => $staff->active,
            'rating' => $staff->rating ?? 0,
            'review_count' => $reviews->count(),
            'total_bookings' => $bookingCount,
            'bio' => $staff->bio,
            'experience_years' => $staff->experience_years ?? null,
            'portfolio' => $staff->portfolio,
            'provider' => $staff->provider ? [
                'id' => $staff->provider->id,
                'name' => $staff->provider->name,
                'display_name' => $staff->provider->display_name,
                'slug' => $staff->provider->slug,
                'logo' => $staff->provider->logo,
            ] : null,
            'location' => $staff->location ? [
                'id' => $staff->location->id,
                'name' => $staff->location->name,
                'address' => $staff->location->address,
            ] : null,
            'services' => $services,
            'reviews' => $reviews,
        ]);
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'sometimes|uuid|exists:providers,id',
            'location_id' => 'sometimes|uuid|exists:locations,id',
            'name' => 'sometimes|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'specializations' => 'nullable',
            'availability' => 'nullable',
            'photo' => 'nullable|image|max:2048',
            'active' => 'sometimes|boolean',
            'role' => 'nullable|string',
        ]);

        $data = $validated;
        
        if ($request->hasFile('photo')) {
            // Delete old media if exists
            if ($staff->photo && \Illuminate\Support\Str::isUuid($staff->photo)) {
                $oldMedia = \App\Models\Media::find($staff->photo);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            } else if ($staff->photo) {
                // Delete old path if exists (backward compatibility)
                Storage::disk('public')->delete($staff->photo);
            }
            
            $media = $this->mediaService->upload($request->file('photo'), [
                'directory' => 'staff/photos',
                'provider_id' => $staff->provider_id,
                'alt_text' => $data['name'] ?? $staff->name . ' photo',
            ]);
            $data['photo'] = $media->id;
        }

        // If specializations is a string, parse it as JSON
        if (isset($data['specializations']) && is_string($data['specializations'])) {
            $data['specializations'] = json_decode($data['specializations'], true);
        }

        // If availability is a string, parse it as JSON
        if (isset($data['availability']) && is_string($data['availability'])) {
            $data['availability'] = json_decode($data['availability'], true);
        }

        $staff->update($data);
        return response()->json($staff->load(['provider', 'location']));
    }

    public function destroy(Staff $staff): JsonResponse
    {
        // Delete media if exists
        if ($staff->photo && \Illuminate\Support\Str::isUuid($staff->photo)) {
            $media = \App\Models\Media::find($staff->photo);
            if ($media) {
                $this->mediaService->delete($media);
            }
        } else if ($staff->photo) {
            // Delete old path if exists (backward compatibility)
            Storage::disk('public')->delete($staff->photo);
        }
        $staff->delete();
        return response()->json(null, 204);
    }

    public function byProvider(string $provider): JsonResponse
    {
        // Resolve provider_id and location_id from provider
        $providerModel = \App\Models\Provider::find($provider);
        if (!$providerModel) {
            return response()->json(['message' => 'Provider not found'], 404);
        }
        
        $staff = Staff::where('provider_id', $providerModel->id)
            ->where('active', true)
            ->get();
        return response()->json($staff);
    }
}
