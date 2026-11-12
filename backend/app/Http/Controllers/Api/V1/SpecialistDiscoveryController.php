<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use App\Models\Invitation;
use App\Models\SpecialistAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SpecialistDiscoveryController extends Controller
{
    /**
     * Search for specialists available for hire
     * This is for providers to discover talent
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
            $query->whereHas('assignments.location', function ($q) use ($validated) {
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
            'assignments.provider',
            'assignments.location',
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
        $currentEmployments = $specialist->assignments()->where('status', 'active')->get();
        $hasCurrentEmployment = $currentEmployments->isNotEmpty();
        $isAvailableForHire = !$hasCurrentEmployment ||
                              $currentEmployments->contains(function ($assignment) {
                                  return in_array($assignment->employment_type, ['freelance', 'contract']);
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
                'current_assignments' => $currentEmployments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'provider_id' => $assignment->provider_id,
                        'provider_name' => $assignment->provider?->display_name,
                        'location_id' => $assignment->location_id,
                        'location_name' => $assignment->location?->name,
                        'role' => $assignment->role,
                        'employment_type' => $assignment->employment_type,
                        'is_primary' => $assignment->is_primary,
                        'started_at' => $assignment->starts_at,
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
        // 1. Not currently employed at any provider
        // 2. Currently employed but as freelance/contract (open to additional work)
        $query = Specialist::where('is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('assignments', function ($sq) {
                    $sq->where('status', 'active');
                })
                ->orWhereHas('assignments', function ($sq) {
                    $sq->where('status', 'active')
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
            'provider_id' => 'required|uuid|exists:providers,id',
            'roles' => 'required|array',
            'roles.*' => 'in:stylist,barber,colorist,nail_artist,makeup_artist,receptionist,manager',
            'employment_type' => 'required|in:full_time,part_time,contract,freelance',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'hourly_rate' => 'nullable|numeric|min:0',
            'message' => 'nullable|string',
        ]);

        // Check if provider already has this specialist
        $existing = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('provider_id', $validated['provider_id'])
            ->where('status', 'ACTIVE')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Specialist is already employed at this provider',
            ], 400);
        }

        // Check if there's already a pending invitation
        $pendingInvitation = Invitation::where('provider_id', $validated['provider_id'])
            ->where('target_id', $specialistId)
            ->where('role', 'specialist')
            ->where('status', 'pending')
            ->first();

        if ($pendingInvitation) {
            return response()->json([
                'message' => 'Invitation already pending for this specialist',
            ], 400);
        }

        // Create invitation using existing Invitation model
        $specialist = Specialist::findOrFail($specialistId);
        
        $issueData = Invitation::issue([
            'provider_id' => $validated['provider_id'],
            'role' => 'specialist',
            'email' => $specialist->email,
            'target_id' => $specialistId,
            'status' => 'pending',
        ], now()->addDays(7));

        $invitation = $issueData['invitation'];
        $rawToken = $issueData['rawToken'];
        
        // TODO: Send notification to specialist with invitation link
        // Assignment details (roles, employment_type, rates) will be set when accepting
        // or through a separate admin interface
        $joinUrl = $invitation->publicJoinUrl($rawToken);

        return response()->json([
            'message' => 'Invitation sent successfully',
            'invitation_id' => $invitation->id,
            'join_url' => $joinUrl,
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

        $invitations = Invitation::where('target_id', $specialistId)
            ->where('role', 'specialist')
            ->where('status', 'pending')
            ->with('provider')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invitation) {
                $metadata = $invitation->metadata ?? [];
                return [
                    'id' => $invitation->id,
                    'provider_id' => $invitation->provider_id,
                    'provider_name' => $invitation->provider?->display_name,
                    'provider_logo' => $invitation->provider?->logo,
                    'role' => $metadata['roles'][0] ?? null,
                    'employment_type' => $metadata['employment_type'] ?? null,
                    'commission_rate' => $metadata['commission_rate'] ?? null,
                    'hourly_rate' => $metadata['hourly_rate'] ?? null,
                    'message' => $metadata['message'] ?? null,
                    'invited_at' => $invitation->created_at,
                    'expires_at' => $invitation->expires_at,
                ];
            });

        return response()->json([
            'invitations' => $invitations,
        ]);
    }

    /**
     * Respond to hiring invitation
     * This is a simplified response endpoint - the actual acceptance flow
     * should go through InvitationController::accept() which handles
     * SpecialistAssignment creation properly
     */
    public function respondToInvitation(Request $request, string $invitationId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,decline',
            'specialist_id' => 'required|uuid',
        ]);

        $invitation = Invitation::where('id', $invitationId)
            ->where('target_id', $validated['specialist_id'])
            ->where('role', 'specialist')
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found',
            ], 404);
        }

        if ($validated['action'] === 'accept') {
            // Mark invitation as accepted
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            // Create SpecialistAssignment with default values
            // Assignment details can be updated later through admin interface
            $provider = \App\Models\Provider::find($invitation->provider_id);
            
            SpecialistAssignment::firstOrCreate(
                [
                    'specialist_id' => $validated['specialist_id'],
                    'provider_id' => $invitation->provider_id,
                    'location_id' => $provider?->primary_location_id,
                ],
                [
                    'role' => 'SPECIALIST',
                    'employment_type' => 'EMPLOYEE',
                    'status' => 'ACTIVE',
                    'starts_at' => now(),
                ]
            );

            return response()->json([
                'message' => 'Invitation accepted',
            ]);
        } else {
            // Decline - mark invitation as expired
            $invitation->update([
                'status' => 'expired',
            ]);

            return response()->json([
                'message' => 'Invitation declined',
            ]);
        }
    }
}
