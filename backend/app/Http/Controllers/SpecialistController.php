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

        // Filter by salon (for salon-specific view)
        if ($request->has('salon_id')) {
            $query->whereHas('salons', function ($q) use ($request) {
                $q->where('salon_id', $request->query('salon_id'))
                  ->where('salon_specialist.active', true);
            });
        }

        // Filter by role at salon
        if ($request->has('role') && $request->has('salon_id')) {
            $query->whereHas('salons', function ($q) use ($request) {
                $q->where('salon_id', $request->query('salon_id'))
                  ->where('salon_specialist.role', $request->query('role'));
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

        $specialists = $query->with(['salons' => function ($q) {
            $q->where('salon_specialist.active', true);
        }, 'provider'])->paginate($perPage);

        return response()->json($specialists);
    }

    public function show(Request $request, string $idOrHandle): JsonResponse
    {
        $specialist = Specialist::where('active', true)
            ->where(function ($q) use ($idOrHandle) {
                $q->where('id', $idOrHandle)->orWhere('handle', $idOrHandle);
            })
            ->with(['provider', 'salons' => function ($q) {
                $q->where('salon_specialist.active', true);
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
            ->orWhereHas('salons', function ($q) use ($specialist) {
                // Match by same salons
                $salonIds = $specialist->salons->pluck('id');
                $q->whereIn('salon_id', $salonIds);
            })
            ->with(['provider', 'salons' => function ($q) {
                $q->where('salon_specialist.active', true);
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
            ] : null,
            'salons' => $specialist->salons->map(function ($salon) {
                return [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'slug' => $salon->slug,
                    'logo' => $salon->logo,
                    'address' => $salon->address,
                    'phone' => $salon->phone,
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
        $specialist->delete();

        return response()->json(null, 204);
    }
}
