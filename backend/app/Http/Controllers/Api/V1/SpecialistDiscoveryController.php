<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SpecialistDiscoveryController extends Controller
{
    /**
     * Search for specialists available for hire
     * This is for salons to discover talent
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string',
            'specialties' => 'nullable|array',
            'specialties.*' => 'string',
            'location' => 'nullable|string',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'min_experience' => 'nullable|integer|min:0',
            'availability' => 'nullable|in:immediate,within_week,within_month,flexible',
            'employment_type' => 'nullable|in:full_time,part_time,contract,freelance',
            'roles' => 'nullable|array',
            'roles.*' => 'in:stylist,barber,colorist,nail_artist,makeup_artist,receptionist,manager',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Specialist::withCount(['customerRelationships as follower_count' => function ($q) {
            $q->where('is_following', true);
        }])
        ->withCount(['customerRelationships as favorite_count' => function ($q) {
            $q->where('is_favorite', true);
        }])
        ->withCount(['bookings as total_bookings'])
        ->where('is_active', true);

        // Text search
        if (!empty($validated['query'])) {
            $searchTerm = $validated['query'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', "%{$searchTerm}%")
                  ->orWhere('handle', 'ilike', "%{$searchTerm}%")
                  ->orWhere('bio', 'ilike', "%{$searchTerm}%")
                  ->orWhereJsonContains('skills', $searchTerm)
                  ->orWhereJsonContains('specialties', $searchTerm);
            });
        }

        // Filter by specialties
        if (!empty($validated['specialties'])) {
            $query->where(function ($q) use ($validated) {
                foreach ($validated['specialties'] as $specialty) {
                    $q->orWhereJsonContains('specialties', $specialty);
                }
            });
        }

        // Filter by minimum rating
        if (isset($validated['min_rating'])) {
            $query->where('rating', '>=', $validated['min_rating']);
        }

        // Filter by minimum experience
        if (isset($validated['min_experience'])) {
            $query->where('years_experience', '>=', $validated['min_experience']);
        }

        // Filter by location (would need geolocation integration)
        if (!empty($validated['location'])) {
            $query->whereHas('salons', function ($q) use ($validated) {
                $q->where('address', 'ilike', "%{$validated['location']}%");
            });
        }

        // Pagination
        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $specialists = $query->orderBy('rating', 'desc')
            ->orderBy('follower_count', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'specialists' => $specialists->items(),
            'pagination' => [
                'current_page' => $specialists->currentPage(),
                'per_page' => $specialists->perPage(),
                'total' => $specialists->total(),
                'last_page' => $specialists->lastPage(),
            ],
        ]);
    }

    /**
     * Get specialist profile for hiring consideration
     */
    public function show(Request $request, string $specialistId): JsonResponse
    {
        $specialist = Specialist::with([
            'customerRelationships' => function ($q) {
                $q->where('is_following', true)->limit(10);
            },
            'reviews' => function ($q) {
                $q->latest()->limit(5);
            },
            'salons' => function ($q) {
                $q->wherePivot('active', true);
            },
        ])
        ->withCount(['customerRelationships as follower_count' => function ($q) {
            $q->where('is_following', true);
        }])
        ->withCount(['customerRelationships as favorite_count' => function ($q) {
            $q->where('is_favorite', true);
        }])
        ->withCount(['bookings as total_bookings'])
        ->findOrFail($specialistId);

        // Calculate employment status
        $currentEmployments = $specialist->salons->where('pivot.active', true);
        $hasCurrentEmployment = $currentEmployments->isNotEmpty();
        $isAvailableForHire = !$hasCurrentEmployment || 
                              $currentEmployments->contains(function ($salon) {
                                  return $salon->pivot->employment_type === 'freelance' || 
                                         $salon->pivot->employment_type === 'contract';
                              });

        return response()->json([
            'specialist' => [
                'id' => $specialist->id,
                'name' => $specialist->name,
                'handle' => $specialist->handle,
                'photo' => $specialist->photo_url,
                'bio' => $specialist->bio,
                'specialties' => $specialist->specialties,
                'skills' => $specialist->skills,
                'years_experience' => $specialist->years_experience,
                'rating' => $specialist->rating,
                'review_count' => $specialist->review_count,
                'certifications' => $specialist->certifications,
                'portfolio' => $specialist->portfolio,
                'languages' => $specialist->languages,
            ],
            'metrics' => [
                'follower_count' => $specialist->follower_count,
                'favorite_count' => $specialist->favorite_count,
                'total_bookings' => $specialist->total_bookings,
            ],
            'employment' => [
                'is_available_for_hire' => $isAvailableForHire,
                'current_salons' => $currentEmployments->map(function ($salon) {
                    return [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'role' => $salon->pivot->roles,
                        'employment_type' => $salon->pivot->employment_type,
                        'is_primary' => $salon->pivot->is_primary_salon,
                        'hired_at' => $salon->pivot->hired_at,
                    ];
                }),
            ],
            'recent_followers' => $specialist->customerRelationships->map(function ($rel) {
                return [
                    'customer' => $rel->customer,
                    'relationship_score' => $rel->relationship_score,
                    'total_bookings' => $rel->total_bookings,
                ];
            }),
            'recent_reviews' => $specialist->reviews,
        ]);
    }

    /**
     * Get specialists available for hire (open to new opportunities)
     */
    public function available(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        // Specialists who are either:
        // 1. Not currently employed at any salon
        // 2. Currently employed but as freelance/contract (open to additional work)
        $query = Specialist::where('is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('salons', function ($sq) {
                    $sq->where('active', true);
                })
                ->orWhereHas('salons', function ($sq) {
                    $sq->where('active', true)
                       ->whereIn('employment_type', ['freelance', 'contract']);
                });
            })
            ->withCount(['customerRelationships as follower_count' => function ($q) {
                $q->where('is_following', true);
            }])
            ->withCount(['bookings as total_bookings']);

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $specialists = $query->orderBy('rating', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'specialists' => $specialists->items(),
            'pagination' => [
                'current_page' => $specialists->currentPage(),
                'per_page' => $specialists->perPage(),
                'total' => $specialists->total(),
                'last_page' => $specialists->lastPage(),
            ],
        ]);
    }

    /**
     * Send hiring invitation to a specialist
     */
    public function invite(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'required|uuid|exists:salons,id',
            'roles' => 'required|array',
            'roles.*' => 'in:stylist,barber,colorist,nail_artist,makeup_artist,receptionist,manager',
            'employment_type' => 'required|in:full_time,part_time,contract,freelance',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'hourly_rate' => 'nullable|numeric|min:0',
            'message' => 'nullable|string',
        ]);

        // Check if salon already has this specialist
        $existing = \DB::table('salon_specialist')
            ->where('specialist_id', $specialistId)
            ->where('salon_id', $validated['salon_id'])
            ->where('active', true)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Specialist is already employed at this salon',
            ], 400);
        }

        // Create invitation record (you may have a separate invitations table)
        // For now, we'll create a pending salon_specialist record
        \DB::table('salon_specialist')->insert([
            'specialist_id' => $specialistId,
            'salon_id' => $validated['salon_id'],
            'roles' => json_encode($validated['roles']),
            'employment_type' => $validated['employment_type'],
            'commission_rate' => $validated['commission_rate'],
            'hourly_rate' => $validated['hourly_rate'],
            'hiring_status' => 'pending',
            'hiring_notes' => $validated['message'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // TODO: Send notification to specialist

        return response()->json([
            'message' => 'Invitation sent successfully',
        ], 201);
    }

    /**
     * Get hiring invitations for a specialist
     */
    public function invitations(Request $request): JsonResponse
    {
        $specialistId = $request->attributes->get('specialist_id') ?? 
                        $request->query('specialist_id');

        if (!$specialistId) {
            return response()->json([
                'message' => 'Specialist ID required',
            ], 400);
        }

        $invitations = \DB::table('salon_specialist')
            ->where('specialist_id', $specialistId)
            ->where('hiring_status', 'pending')
            ->join('salons', 'salon_specialist.salon_id', '=', 'salons.id')
            ->select(
                'salons.id',
                'salons.name',
                'salons.logo',
                'salons.address',
                'salon_specialist.roles',
                'salon_specialist.employment_type',
                'salon_specialist.commission_rate',
                'salon_specialist.hourly_rate',
                'salon_specialist.hiring_notes',
                'salon_specialist.created_at as invited_at'
            )
            ->get();

        return response()->json([
            'invitations' => $invitations,
        ]);
    }

    /**
     * Respond to hiring invitation
     */
    public function respondToInvitation(Request $request, string $salonId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,decline',
            'specialist_id' => 'required|uuid',
        ]);

        $invitation = \DB::table('salon_specialist')
            ->where('specialist_id', $validated['specialist_id'])
            ->where('salon_id', $salonId)
            ->where('hiring_status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found',
            ], 404);
        }

        if ($validated['action'] === 'accept') {
            \DB::table('salon_specialist')
                ->where('specialist_id', $validated['specialist_id'])
                ->where('salon_id', $salonId)
                ->update([
                    'hiring_status' => 'active',
                    'hired_at' => now(),
                    'active' => true,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Invitation accepted',
            ]);
        } else {
            \DB::table('salon_specialist')
                ->where('specialist_id', $validated['specialist_id'])
                ->where('salon_id', $salonId)
                ->delete();

            return response()->json([
                'message' => 'Invitation declined',
            ]);
        }
    }
}
