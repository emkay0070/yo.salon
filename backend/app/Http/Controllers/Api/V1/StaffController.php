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
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);
        
        $query = Staff::with('salon')->where('salon_id', $salonId);
        $staff = $query->get();
        return response()->json($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) return response()->json(['message' => 'No salon associated with your account'], 403);

        // Wrap in transaction to prevent race conditions on capacity checks
        return DB::transaction(function () use ($request, $salonId) {
            // Check if salon can add more staff members (service-layer enforcement)
            // This check is inside the transaction to prevent race conditions
            if (!$this->capabilityResolver->canAdd($salonId, 'STAFF_SEAT')) {
                $limit = $this->capabilityResolver->limit($salonId, 'STAFF_SEAT');
                $current = $this->capabilityResolver->usage($salonId, 'STAFF_SEAT');
                
                return response()->json([
                    'message' => 'Staff limit reached',
                    'error' => 'STAFF_LIMIT_EXCEEDED',
                    'limit' => $limit,
                    'current' => $current,
                    'remaining' => $this->capabilityResolver->remaining($salonId, 'STAFF_SEAT'),
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
            ]);

            $data = $validated;
            $data['salon_id'] = $salonId;

            // Set active to true by default if not provided
            if (!isset($data['active'])) {
                $data['active'] = true;
            }

            if ($request->hasFile('photo')) {
                $media = $this->mediaService->upload($request->file('photo'), [
                    'directory' => 'staff/photos',
                    'provider_id' => auth()->user()->currentSalon()?->provider_id,
                    'salon_id' => $salonId,
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
            return response()->json($staffMember->load('salon'), 201);
        });
    }

    public function show(Staff $staff): JsonResponse
    {
        $staff->load(['salon', 'salon.provider']);

        // Get services offered by this staff member.
        // Since staff.id ≠ specialist.id in the new architecture,
        // we resolve the Specialist identity via SpecialistAssignment.
        $assignment = \App\Models\SpecialistAssignment::where('staff_id', $staff->id)
            ->where('salon_id', $staff->salon_id)
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
            'role' => $staff->role,
            'phone' => $staff->phone,
            'email' => $staff->email,
            'photo' => $staff->photo,
            'photo_url' => $staff->photo_url,  // FIXED: previously missing, drawer relied on this
            'specializations' => $staff->specializations,
            'availability' => $staff->availability,
            'active' => $staff->active,
            'rating' => $staff->rating ?? 0,
            'review_count' => $reviews->count(),
            'total_bookings' => $bookingCount,
            'bio' => $staff->bio,
            'experience_years' => $staff->experience_years ?? null,
            'portfolio' => $staff->portfolio,
            'salon' => $staff->salon ? [
                'id' => $staff->salon->id,
                'name' => $staff->salon->name,
                'slug' => $staff->salon->slug,
                'logo' => $staff->salon->logo,
                'address' => $staff->salon->address,
                'phone' => $staff->salon->phone,
            ] : null,
            'provider' => $staff->salon && $staff->salon->provider ? [
                'id' => $staff->salon->provider->id,
                'name' => $staff->salon->provider->name,
                'display_name' => $staff->salon->provider->display_name,
                'slug' => $staff->salon->provider->slug,
                'logo' => $staff->salon->provider->logo,
            ] : null,
            'services' => $services,
            'reviews' => $reviews,
        ]);
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'sometimes|uuid|exists:salons,id',
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
                'provider_id' => auth()->user()->currentSalon()?->provider_id,
                'salon_id' => $staff->salon_id,
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
        return response()->json($staff->load('salon'));
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

    public function bySalon(string $salon): JsonResponse
    {
        $staff = Staff::where('salon_id', $salon)
            ->where('active', true)
            ->get();
        return response()->json($staff);
    }
}
