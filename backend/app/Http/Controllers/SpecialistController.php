<?php

namespace App\Http\Controllers;

use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SpecialistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Specialist::where('active', true);

        // Global search by name or handle
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', "%{$searchTerm}%")
                  ->orWhere('handle', 'ilike', "%{$searchTerm}%")
                  ->orWhere('bio', 'ilike', "%{$searchTerm}%")
                  ->orWhereJsonContains('specialties', $searchTerm)
                  ->orWhereJsonContains('skills', $searchTerm);
            });
        }

        // Filter by specialty
        if ($request->has('specialty')) {
            $query->whereJsonContains('specialties', $request->query('specialty'));
        }

        // Filter by skill
        if ($request->has('skill')) {
            $query->whereJsonContains('skills', $request->query('skill'));
        }

        // Filter by minimum rating
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->query('min_rating'));
        }

        // Filter by minimum experience
        if ($request->has('min_experience')) {
            $query->where('years_experience', '>=', $request->query('min_experience'));
        }

        // Filter by language
        if ($request->has('language')) {
            $query->whereJsonContains('languages', $request->query('language'));
        }

        // Filter by provider/location (canonical via SpecialistAssignment)
        if ($request->has('provider_id')) {
            $query->whereHas('assignments', function ($q) use ($request) {
                $q->where('provider_id', $request->query('provider_id'))
                  ->where('status', 'ACTIVE');
            });
        }

        if ($request->has('location_id')) {
            $query->whereHas('assignments', function ($q) use ($request) {
                $q->where('location_id', $request->query('location_id'))
                  ->where('status', 'ACTIVE');
            });
        }

        // Filter by role at provider/location
        if ($request->has('role') && ($request->has('provider_id') || $request->has('location_id'))) {
            $query->whereHas('assignments', function ($q) use ($request) {
                $q->where('role', $request->query('role'))
                  ->where('status', 'ACTIVE');
                if ($request->has('provider_id')) {
                    $q->where('provider_id', $request->query('provider_id'));
                }
                if ($request->has('location_id')) {
                    $q->where('location_id', $request->query('location_id'));
                }
            });
        }

        // Filter by provider
        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->query('provider_id'));
        }

        // Sorting options
        $sortBy = $request->query('sort_by', 'rating');
        $sortOrder = $request->query('sort_order', 'desc');

        $validSortFields = ['rating', 'review_count', 'years_experience', 'follower_count', 'name', 'created_at'];
        if (in_array($sortBy, $validSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('rating', 'desc');
        }

        $perPage = $request->query('per_page', 20);
        $perPage = min($perPage, 100); // Max 100 per page

        $specialists = $query->with(['assignments' => function ($q) {
            $q->where('status', 'ACTIVE')->with('provider', 'location');
        }, 'provider'])->paginate($perPage);

        return response()->json($specialists);
    }

    public function show(Request $request, string $idOrHandle): JsonResponse
    {
        $specialist = Specialist::where('active', true)
            ->where(function ($q) use ($idOrHandle) {
                $q->where('id', $idOrHandle)->orWhere('handle', $idOrHandle);
            })
            ->with(['provider.providerType', 'assignments' => function ($q) {
                $q->where('status', 'ACTIVE')->with('provider.providerType', 'location');
            }])
            ->first();

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Get services offered by this specialist with skill levels
        $services = \DB::table('services')
            ->join('specialist_service', 'services.id', '=', 'specialist_service.service_id')
            ->where('specialist_service.specialist_id', $specialist->id)
            ->where('services.active', true)
            ->select(
                'services.id',
                'services.name',
                'services.description',
                'services.category',
                'services.price',
                'services.duration',
                'services.image_path',
                'specialist_service.skill_level',
                'specialist_service.price_override',
                'specialist_service.is_primary'
            )
            ->get()
            ->map(function ($service) {
                $service->image_url = $service->image_path ? asset('storage/' . $service->image_path) : null;
                return $service;
            });

        // Get specialist schedule
        $schedule = \App\Models\SpecialistSchedule::where('specialist_id', $specialist->id)
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('expires_date')
                    ->orWhere('expires_date', '>=', now()->toDateString());
            })
            ->orderByDesc('effective_date')
            ->get();

        $operatingHours = [];
        for ($day = 0; $day <= 6; $day++) {
            $daySchedule = $schedule->firstWhere('day_of_week', $day);
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $operatingHours[] = [
                'day' => $dayNames[$day],
                'day_of_week' => $day,
                'start' => $daySchedule && $daySchedule->is_available ? $daySchedule->start_time : null,
                'end' => $daySchedule && $daySchedule->is_available ? $daySchedule->end_time : null,
                'break_start' => $daySchedule ? $daySchedule->break_start_time : null,
                'break_end' => $daySchedule ? $daySchedule->break_end_time : null,
                'is_available' => $daySchedule ? $daySchedule->is_available : false,
            ];
        }

        // Get reviews for this specialist
        $reviews = \DB::table('reviews')
            ->join('customers', 'reviews.customer_id', '=', 'customers.id')
            ->where('reviews.specialist_id', $specialist->id)
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

        // Get booking count
        $bookingCount = \DB::table('bookings')
            ->where('specialist_id', $specialist->id)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Get specialist activity feed
        $activities = $specialist->activities()
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'message' => $activity->message,
                    'data' => $activity->data,
                    'created_at' => $activity->created_at,
                ];
            });

        // Get similar specialists based on specialties and services
        $similarSpecialists = Specialist::where('active', true)
            ->where('id', '!=', $specialist->id)
            ->where(function ($q) use ($specialist) {
                // Match by specialties
                $specialties = is_array($specialist->specialties) ? $specialist->specialties : [];
                if (!empty($specialties)) {
                    foreach ($specialties as $specialty) {
                        $q->orWhereJsonContains('specialties', $specialty);
                    }
                }
            })
            ->orWhereHas('assignments', function ($q) use ($specialist) {
                // Match by same providers
                $providerIds = $specialist->assignments()->pluck('provider_id');
                $q->whereIn('provider_id', $providerIds);
            })
            ->with(['provider', 'assignments' => function ($q) {
                $q->where('specialist_assignments.status', 'active')->with('provider');
            }])
            ->limit(4)
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'handle' => $s->handle,
                    'role' => $s->role,
                    'photo' => $s->photo_url,
                    'rating' => $s->rating ?? 0,
                    'review_count' => $s->review_count ?? 0,
                    'specialties' => $s->specialties,
                    'provider' => $s->provider ? [
                        'id' => $s->provider->id,
                        'name' => $s->provider->display_name,
                    ] : null,
                ];
            });

        return response()->json([
            'id' => $specialist->id,
            'name' => $specialist->name,
            'handle' => $specialist->handle,
            'role' => $specialist->role,
            'bio' => $specialist->bio,
            'specialties' => $specialist->specialties,
            'languages' => $specialist->languages,
            'qualifications' => $specialist->qualifications,
            'portfolio' => $specialist->portfolio,
            'photo' => $specialist->photo_url,
            'profile_image' => $specialist->profile_image,
            'skills' => $specialist->skills,
            'years_experience' => $specialist->years_experience,
            'certifications' => $specialist->certifications,
            'rating' => $specialist->rating ?? 0,
            'review_count' => $reviews->count(),
            'total_bookings' => $bookingCount,
            'follower_count' => $specialist->follower_count ?? 0,
            'favorite_count' => $specialist->favorite_count ?? 0,
            'achievements' => $specialist->achievements,
            'career_timeline' => $specialist->career_timeline,
            'active' => $specialist->is_active,
            'provider' => $specialist->provider ? [
                'id' => $specialist->provider->id,
                'name' => $specialist->provider->name,
                'display_name' => $specialist->provider->display_name,
                'slug' => $specialist->provider->slug,
                'logo' => $specialist->provider->logo,
                'provider_type' => $specialist->provider->providerTypeKey(),
            ] : null,
            'salons' => $specialist->assignments->map(function ($assignment) {
                return [
                    'id' => $assignment->provider->id,
                    'name' => $assignment->provider->display_name,
                    'slug' => $assignment->provider->slug,
                    'logo' => $assignment->provider->logo_url,
                    'address' => $assignment->location?->address,
                    'phone' => $assignment->location?->phone ?? $assignment->provider->phone,
                ];
            }),
            'services' => $services,
            'operating_hours' => $operatingHours,
            'reviews' => $reviews,
            'similar_specialists' => $similarSpecialists,
            'activities' => $activities,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'handle' => 'nullable|string|unique:specialists,handle',
            'bio' => 'nullable|string',
            'specialties' => 'nullable|array',
            'languages' => 'nullable|array',
            'qualifications' => 'nullable|array',
            'portfolio' => 'nullable|array',
            'photo' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        $specialist = Specialist::create($validated);

        return response()->json($specialist, 201);
    }

    public function update(Request $request, Specialist $specialist): JsonResponse
    {
        if (!$this->managesSpecialist($specialist)) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'handle' => 'nullable|string|unique:specialists,handle,' . $specialist->id,
            'bio' => 'nullable|string',
            'specialties' => 'nullable|array',
            'languages' => 'nullable|array',
            'qualifications' => 'nullable|array',
            'portfolio' => 'nullable|array',
            'photo' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        $specialist->update($validated);

        return response()->json($specialist);
    }

    public function destroy(Specialist $specialist): JsonResponse
    {
        if (!$this->managesSpecialist($specialist)) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $specialist->delete();

        return response()->json(null, 204);
    }

    /**
     * Update the provider-scoped assignment (role, active status, rates).
     * This edits the relationship, not the global specialist identity.
     */
    public function updateAssignment(Request $request, string $provider, string $specialist): JsonResponse
    {
        $providerModel = $this->resolveManagedProvider($provider);
        if (!$providerModel) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        $assignment = \App\Models\SpecialistAssignment::where('provider_id', $providerModel->id)
            ->where('specialist_id', $specialist)
            ->latest('created_at')
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Specialist assignment not found'], 404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|string',
            'employment_type' => 'sometimes|string|in:employee,freelancer,contractor,EMPLOYEE,FREELANCER,CONTRACTOR',
            'is_primary' => 'sometimes|boolean',
            'commission_rate' => 'sometimes|nullable|numeric',
            'hourly_rate' => 'sometimes|nullable|numeric',
            'status' => 'sometimes|string|in:active,inactive,terminated,ACTIVE,INACTIVE,TERMINATED',
            'active' => 'sometimes|boolean',
        ]);

        $data = $validated;

        if (array_key_exists('active', $data)) {
            $data['status'] = $data['active'] ? 'ACTIVE' : 'INACTIVE';
            unset($data['active']);
        }

        if (isset($data['role'])) {
            $roleMap = [
                'owner' => 'OWNER',
                'manager' => 'MANAGER',
                'staff' => 'SPECIALIST',
                'receptionist' => 'SPECIALIST',
                'senior_specialist' => 'SENIOR_SPECIALIST',
                'specialist' => 'SPECIALIST',
                'trainee' => 'TRAINEE',
            ];
            $normalized = strtolower($data['role']);
            $data['role'] = $roleMap[$normalized] ?? strtoupper($data['role']);

            if (!in_array($data['role'], ['OWNER', 'MANAGER', 'SENIOR_SPECIALIST', 'SPECIALIST', 'TRAINEE'], true)) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['role' => ['Invalid assignment role.']],
                ], 422);
            }
        }

        if (isset($data['status'])) {
            $data['status'] = strtoupper($data['status']);
        }

        if (isset($data['employment_type'])) {
            $data['employment_type'] = strtoupper($data['employment_type']);
        }

        $assignment->update($data);

        $specialistModel = $assignment->specialist;
        $specialistModel->pivot = (object) [
            'role' => $assignment->role,
            'employment_type' => $assignment->employment_type,
            'is_primary' => $assignment->is_primary,
            'status' => $assignment->status,
            'assignment_id' => $assignment->id,
        ];

        return response()->json($specialistModel);
    }

    /**
     * Remove a specialist from the provider by deleting the assignment.
     * The global specialist identity is preserved.
     */
    public function destroyAssignment(string $provider, string $specialist): JsonResponse
    {
        $providerModel = $this->resolveManagedProvider($provider);
        if (!$providerModel) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        $assignment = \App\Models\SpecialistAssignment::where('provider_id', $providerModel->id)
            ->where('specialist_id', $specialist)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Specialist assignment not found'], 404);
        }

        $assignment->delete();

        return response()->json(null, 204);
    }

    private function resolveManagedProvider(string $provider): ?\App\Models\Provider
    {
        $providerModel = \App\Models\Provider::where('id', $provider)
            ->orWhere('slug', $provider)
            ->first();

        $current = auth()->user()?->currentProvider();

        if (!$providerModel || !$current || $providerModel->id !== $current->id) {
            return null;
        }

        return $providerModel;
    }

    private function managesSpecialist(Specialist $specialist): bool
    {
        $current = auth()->user()?->currentProvider();

        if (!$current) {
            return false;
        }

        return $specialist->assignments()
            ->where('provider_id', $current->id)
            ->whereIn('status', ['ACTIVE', 'active'])
            ->exists();
    }
}
