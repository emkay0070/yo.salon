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
     * Get active offers for the current salon
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');

        if (!$salonId) {
            return response()->json([
                'message' => 'Salon context not found',
            ], 400);
        }

        $offers = $this->offerService->getActiveOffers($salonId);

        return response()->json([
            'offers' => $offers,
        ]);
    }

    /**
     * Get offers eligible for the current customer
     */
    public function customerOffers(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        if (!$salonId || !$customerId) {
            return response()->json([
                'message' => 'Context not found',
            ], 400);
        }

        $offers = $this->offerService->getCustomerEligibleOffers($customerId, $salonId);

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
            'salon_id' => 'required|uuid',
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
