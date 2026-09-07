<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CustomerSpecialistService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerSpecialistController extends Controller
{
    private CustomerSpecialistService $specialistService;

    public function __construct(CustomerSpecialistService $specialistService)
    {
        $this->specialistService = $specialistService;
    }

    /**
     * Get customer's followed specialists
     */
    public function followed(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');

        try {
            $relationships = $this->specialistService->getFollowedSpecialists($customerId);

            return response()->json([
                'specialists' => $relationships->map(function ($rel) {
                    return [
                        'specialist' => $rel->specialist,
                        'is_favorite' => $rel->is_favorite,
                        'total_bookings' => $rel->total_bookings,
                        'relationship_score' => $rel->relationship_score,
                        'last_interaction' => $rel->last_interaction_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get followed specialists',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer's favorite specialists
     */
    public function favorites(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');

        try {
            $relationships = $this->specialistService->getFavoriteSpecialists($customerId);

            return response()->json([
                'specialists' => $relationships->map(function ($rel) {
                    return [
                        'specialist' => $rel->specialist,
                        'total_bookings' => $rel->total_bookings,
                        'relationship_score' => $rel->relationship_score,
                        'last_interaction' => $rel->last_interaction_at,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get favorite specialists',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle follow status for a specialist
     * Requires provider_id to ensure workspace-specific relationship
     */
    public function toggleFollow(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $customerId = $request->attributes->get('customer_id');

        try {
            $relationship = $this->specialistService->toggleFollow(
                $customerId,
                $specialistId,
                $validated['provider_id']
            );

            return response()->json([
                'message' => $relationship->is_following ? 'Now following specialist' : 'Unfollowed specialist',
                'is_following' => $relationship->is_following,
                'is_favorite' => $relationship->is_favorite,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to toggle follow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle favorite status for a specialist
     * Requires provider_id to ensure workspace-specific relationship
     */
    public function toggleFavorite(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $customerId = $request->attributes->get('customer_id');

        try {
            $relationship = $this->specialistService->toggleFavorite(
                $customerId,
                $specialistId,
                $validated['provider_id']
            );

            return response()->json([
                'message' => $relationship->is_favorite ? 'Added to favorites' : 'Removed from favorites',
                'is_favorite' => $relationship->is_favorite,
                'is_following' => $relationship->is_following,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to toggle favorite',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set rating for a specialist
     * Requires provider_id to ensure workspace-specific relationship
     */
    public function setRating(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $customerId = $request->attributes->get('customer_id');

        try {
            $relationship = $this->specialistService->setRating(
                $customerId,
                $specialistId,
                $validated['provider_id'],
                $validated['rating']
            );

            return response()->json([
                'message' => 'Rating saved',
                'rating' => $relationship->rating_given,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save rating',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get relationship with a specific specialist
     */
    public function getRelationship(Request $request, string $specialistId): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');

        try {
            $relationship = \App\Models\CustomerSpecialist::where('customer_id', $customerId)
                ->where('specialist_id', $specialistId)
                ->with('specialist')
                ->first();

            if (!$relationship) {
                return response()->json([
                    'relationship' => null,
                    'specialist' => \App\Models\Specialist::find($specialistId),
                ]);
            }

            return response()->json([
                'relationship' => [
                    'is_favorite' => $relationship->is_favorite,
                    'is_following' => $relationship->is_following,
                    'rating_given' => $relationship->rating_given,
                    'total_bookings' => $relationship->total_bookings,
                    'total_spent' => $relationship->total_spent,
                    'relationship_score' => $relationship->relationship_score,
                    'strength_level' => $relationship->getStrengthLevel(),
                    'last_interaction' => $relationship->last_interaction_at,
                ],
                'specialist' => $relationship->specialist,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get relationship',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
