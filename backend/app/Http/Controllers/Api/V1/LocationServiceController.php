<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Service;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Domain\Catalog\Engines\PricingEngine;
use App\Domain\Catalog\Engines\BookingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LocationServiceController
 *
 * Manages which services a specific location offers
 * and any location-level overrides (price, duration, booking rules).
 *
 * Routes:
 *   GET    /api/v1/locations/{location}/services              → index (available services at location)
 *   POST   /api/v1/locations/{location}/services/{service}    → attach (enable service at location)
 *   PATCH  /api/v1/locations/{location}/services/{service}    → update (change overrides)
 *   DELETE /api/v1/locations/{location}/services/{service}    → detach (disable service at location)
 *   GET    /api/v1/locations/{location}/services/{service}/policy → policy (resolved booking policy)
 */
class LocationServiceController extends Controller
{
    /**
     * List all services available at this location, with resolved effective pricing.
     */
    public function index(Location $location, BranchCatalogQuery $catalogQuery): JsonResponse
    {
        $services = $catalogQuery->queryAvailableServices($location)
            ->with('provider:id,display_name,slug')
            ->get()
            ->map(function (Service $service) use ($location) {
                $pricing = new PricingEngine($location, $service);
                $booking = new BookingEngine($location, $service);
                return [
                    'id'                     => $service->id,
                    'name'                   => $service->name,
                    'description'            => $service->description,
                    'category'               => $service->category,
                    'image_url'              => $service->image_url,
                    'provider_price'         => $service->price,
                    'provider_duration'      => $service->duration,
                    // Resolved effective values for this location
                    'effective_price'        => $pricing->effectivePrice(),
                    'effective_duration'     => $booking->effectiveDuration(),
                    'buffer_before'          => $booking->bufferBefore(),
                    'buffer_after'           => $booking->bufferAfter(),
                    'online_booking_enabled' => $booking->acceptsOnlineBooking(),
                    'requires_deposit'       => $pricing->requiresDeposit(),
                    'deposit_amount'         => $pricing->depositAmount(),
                    // Raw pivot for admin
                    'pivot' => [
                        'price_override'    => $service->pivot_price_override,
                        'duration_override' => $service->pivot_duration_override,
                        'notes'             => $service->pivot_notes,
                    ],
                ];
            });

        return response()->json([
            'location' => ['id' => $location->id, 'name' => $location->name],
            'services' => $services,
        ]);
    }

    /**
     * Enable a service at this location (create location_service pivot record).
     */
    public function attach(Request $request, Location $location, Service $service): JsonResponse
    {
        // Verify the service belongs to the same provider as the location
        if ($service->provider_id !== $location->provider_id) {
            return response()->json([
                'message' => 'Service does not belong to the same provider as this location.',
            ], 422);
        }

        // Technically, under open-world inheritance, if no row exists, it's ALREADY assigned.
        // We only enforce "already assigned explicitly" to prevent duplicate identical rows.
        $exists = \Illuminate\Support\Facades\DB::table('location_service')
            ->where(['location_id' => $location->id, 'service_id' => $service->id])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Service overrides already exist for this location.'], 409);
        }

        $validated = $request->validate([
            'price_override'              => 'nullable|numeric|min:0',
            'duration_override'           => 'nullable|integer|min:1',
            'buffer_before_override'      => 'nullable|integer|min:0',
            'buffer_after_override'       => 'nullable|integer|min:0',
            'min_booking_notice_override' => 'nullable|integer|min:0',
            'cancellation_cutoff_override'=> 'nullable|integer|min:0',
            'deposit_required_override'   => 'nullable|boolean',
            'online_booking_enabled'      => 'nullable|boolean',
            'is_available'                => 'nullable|boolean',
            'notes'                       => 'nullable|string|max:500',
        ]);

        \Illuminate\Support\Facades\DB::table('location_service')->updateOrInsert(
            ['location_id' => $location->id, 'service_id' => $service->id],
            $validated
        );

        // Fetch the resolved service using the Query to instantiate the Engines
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($location)->where('services.id', $service->id)->first();
        
        $pricing = new PricingEngine($location->fresh(), $resolvedService);
        $booking = new BookingEngine($location->fresh(), $resolvedService);

        return response()->json([
            'message' => 'Service enabled at this location.',
            'policy'  => [
                'effective_price'         => $pricing->effectivePrice(),
                'effective_duration'      => $booking->effectiveDuration(),
                'buffer_before'           => $booking->bufferBefore(),
                'buffer_after'            => $booking->bufferAfter(),
                'min_booking_notice'      => $booking->minBookingNoticeHours(),
                'cancellation_cutoff'     => $booking->cancellationCutoffHours(),
                'is_available'            => $booking->isAvailableAtBranch(),
                'accepts_online_booking'  => $booking->acceptsOnlineBooking(),
                'requires_deposit'        => $pricing->requiresDeposit(),
                'deposit_amount'          => $pricing->depositAmount(),
            ],
        ], 201);
    }

    /**
     * Update location-level overrides for a service.
     */
    public function update(Request $request, Location $location, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'price_override'              => 'nullable|numeric|min:0',
            'duration_override'           => 'nullable|integer|min:1',
            'buffer_before_override'      => 'nullable|integer|min:0',
            'buffer_after_override'       => 'nullable|integer|min:0',
            'min_booking_notice_override' => 'nullable|integer|min:0',
            'cancellation_cutoff_override'=> 'nullable|integer|min:0',
            'deposit_required_override'   => 'nullable|boolean',
            'online_booking_enabled'      => 'nullable|boolean',
            'is_available'                => 'nullable|boolean',
            'notes'                       => 'nullable|string|max:500',
        ]);

        \Illuminate\Support\Facades\DB::table('location_service')->where([
            'location_id' => $location->id,
            'service_id' => $service->id
        ])->update($validated);
        
        // Fetch the resolved service using the Query to instantiate the Engines
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($location)->where('services.id', $service->id)->first();
        
        $pricing = new PricingEngine($location->fresh(), $resolvedService);
        $booking = new BookingEngine($location->fresh(), $resolvedService);

        return response()->json([
            'message' => 'Service overrides updated.',
            'policy'  => [
                'effective_price'         => $pricing->effectivePrice(),
                'effective_duration'      => $booking->effectiveDuration(),
                'buffer_before'           => $booking->bufferBefore(),
                'buffer_after'            => $booking->bufferAfter(),
                'min_booking_notice'      => $booking->minBookingNoticeHours(),
                'cancellation_cutoff'     => $booking->cancellationCutoffHours(),
                'is_available'            => $booking->isAvailableAtBranch(),
                'accepts_online_booking'  => $booking->acceptsOnlineBooking(),
                'requires_deposit'        => $pricing->requiresDeposit(),
                'deposit_amount'          => $pricing->depositAmount(),
            ],
        ], 200);
    }

    /**
     * Disable a service at this location.
     */
    public function detach(Location $location, Service $service): JsonResponse
    {
        \Illuminate\Support\Facades\DB::table('location_service')->where([
            'location_id' => $location->id,
            'service_id' => $service->id
        ])->delete();
        
        return response()->json(['message' => 'Service disabled at this location.'], 200);
    }

    /**
     * Get the fully resolved booking policy for a service at this location.
     * This is what the booking UI calls before showing the Book button.
     */
    public function policy(Location $location, Service $service): JsonResponse
    {
        if ($service->provider_id !== $location->provider_id) {
            return response()->json([
                'is_available' => false,
                'reason'       => 'Service is not offered by this provider.',
            ], 200);
        }

        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($location)->where('services.id', $service->id)->first();
        
        $pricing = new PricingEngine($location, $resolvedService);
        $booking = new BookingEngine($location, $resolvedService);

        return response()->json([
            'effective_price'         => $pricing->effectivePrice(),
            'effective_duration'      => $booking->effectiveDuration(),
            'buffer_before'           => $booking->bufferBefore(),
            'buffer_after'            => $booking->bufferAfter(),
            'min_booking_notice'      => $booking->minBookingNoticeHours(),
            'cancellation_cutoff'     => $booking->cancellationCutoffHours(),
            'is_available'            => $booking->isAvailableAtBranch(),
            'accepts_online_booking'  => $booking->acceptsOnlineBooking(),
            'requires_deposit'        => $pricing->requiresDeposit(),
            'deposit_amount'          => $pricing->depositAmount(),
        ], 200);
    }
}
