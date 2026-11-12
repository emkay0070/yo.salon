<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OfferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OfferController extends Controller
{
    protected OfferService $offerService;

    public function __construct(OfferService $offerService)
    {
        $this->offerService = $offerService;
    }

    /**
     * Get active offers for the current provider/location
     */
    public function index(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $locationId = $request->attributes->get('location_id');

        if (!$providerId) {
            return response()->json([
                'message' => 'Provider context not found',
            ], 400);
        }

        $offers = $this->offerService->getActiveOffers($providerId, $locationId);

        return response()->json([
            'offers' => $offers,
        ]);
    }

    /**
     * Get offers eligible for the current customer
     */
    public function customerOffers(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $locationId = $request->attributes->get('location_id');
        $customerId = $request->attributes->get('customer_id');

        if (!$providerId || !$customerId) {
            return response()->json([
                'message' => 'Context not found',
            ], 400);
        }

        $offers = $this->offerService->getCustomerEligibleOffers($customerId, $providerId, $locationId);

        return response()->json([
            'offers' => $offers,
        ]);
    }

    /**
     * Create a new offer (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|enum:percentage,fixed,buy_x_get_y',
            'discount_value' => 'required|numeric|min:0',
            'discount_config' => 'nullable|array',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'terms' => 'nullable|string',
            'image' => 'nullable|string',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        // Verify the authenticated user owns this provider
        $user = $request->user();
        if (!$user->providers()->where('id', $validated['provider_id'])->exists()) {
            return response()->json([
                'message' => 'Unauthorized: you do not own this provider',
            ], 403);
        }

        // If location_id is provided, verify it belongs to the provider
        if (isset($validated['location_id'])) {
            $location = \App\Models\Location::find($validated['location_id']);
            if (!$location || $location->provider_id !== $validated['provider_id']) {
                return response()->json([
                    'message' => 'Invalid location: must belong to the provider',
                ], 422);
            }
        }

        try {
            $offer = $this->offerService->createOffer($validated);

            return response()->json([
                'message' => 'Offer created successfully',
                'offer' => $offer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create offer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an offer (admin only)
     */
    public function update(Request $request, string $offerId): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'sometimes|enum:percentage,fixed,buy_x_get_y',
            'discount_value' => 'sometimes|numeric|min:0',
            'discount_config' => 'nullable|array',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'terms' => 'nullable|string',
            'image' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        try {
            $offer = $this->offerService->updateOffer($offerId, $validated);

            return response()->json([
                'message' => 'Offer updated successfully',
                'offer' => $offer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update offer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an offer (admin only)
     */
    public function destroy(string $offerId): JsonResponse
    {
        try {
            $this->offerService->deleteOffer($offerId);

            return response()->json([
                'message' => 'Offer deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete offer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
