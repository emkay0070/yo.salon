<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use App\Models\Customer;
use App\Services\CustomerSpecialistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialistFollowController extends Controller
{
    private CustomerSpecialistService $customerSpecialistService;

    public function __construct(CustomerSpecialistService $customerSpecialistService)
    {
        $this->customerSpecialistService = $customerSpecialistService;
    }

    /**
     * Follow a specialist
     * Requires provider_id for workspace-specific relationship tracking
     */
    public function follow(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $specialist = Specialist::findOrFail($specialistId);
        $user = auth()->user();

        // Handle portal authentication - get customer from portal account
        if ($user instanceof \App\Models\PortalAccount) {
            $customer = $user->customer;
        } else {
            $customer = $user;
        }

        if (!$customer instanceof Customer) {
            return response()->json(['message' => 'Only customers can follow specialists'], 403);
        }

        try {
            $relationship = $this->customerSpecialistService->toggleFollow(
                $customer->id,
                $specialistId,
                $validated['provider_id']
            );

            // Update specialist follower count (now provider-aware)
            $specialist->follower_count = $specialist->customerRelationships()->where('is_following', true)->count();
            $specialist->save();

            return response()->json([
                'message' => 'Specialist followed successfully',
                'is_following' => $relationship->is_following,
                'follower_count' => $specialist->follower_count,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to follow specialist', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unfollow a specialist
     * Requires provider_id for workspace-specific relationship tracking
     */
    public function unfollow(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $specialist = Specialist::findOrFail($specialistId);
        $user = auth()->user();

        // Handle portal authentication - get customer from portal account
        if ($user instanceof \App\Models\PortalAccount) {
            $customer = $user->customer;
        } else {
            $customer = $user;
        }

        if (!$customer instanceof Customer) {
            return response()->json(['message' => 'Only customers can unfollow specialists'], 403);
        }

        try {
            $relationship = $this->customerSpecialistService->toggleFollow(
                $customer->id,
                $specialistId,
                $validated['provider_id']
            );

            // Update specialist follower count (now provider-aware)
            $specialist->follower_count = $specialist->customerRelationships()->where('is_following', true)->count();
            $specialist->save();

            return response()->json([
                'message' => 'Specialist unfollowed successfully',
                'is_following' => $relationship->is_following,
                'follower_count' => $specialist->follower_count,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to unfollow specialist', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Favorite a specialist
     * Requires provider_id for workspace-specific relationship tracking
     */
    public function favorite(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $specialist = Specialist::findOrFail($specialistId);
        $user = auth()->user();

        // Handle portal authentication - get customer from portal account
        if ($user instanceof \App\Models\PortalAccount) {
            $customer = $user->customer;
        } else {
            $customer = $user;
        }

        if (!$customer instanceof Customer) {
            return response()->json(['message' => 'Only customers can favorite specialists'], 403);
        }

        try {
            $relationship = $this->customerSpecialistService->toggleFavorite(
                $customer->id,
                $specialistId,
                $validated['provider_id']
            );

            // Update specialist favorite count (now provider-aware)
            $specialist->favorite_count = $specialist->customerRelationships()->where('is_favorite', true)->count();
            $specialist->save();

            return response()->json([
                'message' => 'Specialist favorited successfully',
                'is_favorite' => $relationship->is_favorite,
                'favorite_count' => $specialist->favorite_count,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to favorite specialist', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unfavorite a specialist
     * Requires provider_id for workspace-specific relationship tracking
     */
    public function unfavorite(Request $request, string $specialistId): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
        ]);

        $specialist = Specialist::findOrFail($specialistId);
        $user = auth()->user();

        // Handle portal authentication - get customer from portal account
        if ($user instanceof \App\Models\PortalAccount) {
            $customer = $user->customer;
        } else {
            $customer = $user;
        }

        if (!$customer instanceof Customer) {
            return response()->json(['message' => 'Only customers can unfavorite specialists'], 403);
        }

        try {
            $relationship = $this->customerSpecialistService->toggleFavorite(
                $customer->id,
                $specialistId,
                $validated['provider_id']
            );

            // Update specialist favorite count (now provider-aware)
            $specialist->favorite_count = $specialist->customerRelationships()->where('is_favorite', true)->count();
            $specialist->save();

            return response()->json([
                'message' => 'Specialist unfavorited successfully',
                'is_favorite' => $relationship->is_favorite,
                'favorite_count' => $specialist->favorite_count,
                'provider_id' => $relationship->provider_id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to unfavorite specialist', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get follow/favorite status for a specialist
     * Optional provider_id for workspace-specific status
     */
    public function status(Request $request, string $specialistId): JsonResponse
    {
        $specialist = Specialist::findOrFail($specialistId);
        $user = auth()->user();

        // Handle portal authentication - get customer from portal account
        if ($user instanceof \App\Models\PortalAccount) {
            $customer = $user->customer;
        } else {
            $customer = $user;
        }

        if (!$customer instanceof Customer) {
            return response()->json([
                'is_following' => false,
                'is_favorite' => false,
                'follower_count' => $specialist->follower_count,
                'favorite_count' => $specialist->favorite_count,
            ]);
        }

        $providerId = $request->query('provider_id');
        
        $query = $customer->specialists()->where('specialist_id', $specialistId);
        
        if ($providerId) {
            $query->wherePivot('provider_id', $providerId);
        }

        $relationship = $query->first();

        return response()->json([
            'is_following' => $relationship?->pivot->is_following ?? false,
            'is_favorite' => $relationship?->pivot->is_favorite ?? false,
            'follower_count' => $specialist->follower_count,
            'favorite_count' => $specialist->favorite_count,
            'provider_id' => $relationship?->pivot->provider_id ?? null,
        ]);
    }
}
