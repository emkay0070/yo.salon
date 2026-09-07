<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\Service;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Domain\Catalog\Engines\PricingEngine;
use App\Domain\Catalog\Engines\BookingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SalonServiceController
 *
 * Manages which services a specific salon branch offers
 * and any branch-level overrides (price, duration, booking rules).
 *
 * Routes:
 *   GET    /api/v1/salons/{salon}/services              → index (available services at branch)
 *   POST   /api/v1/salons/{salon}/services/{service}    → attach (enable service at branch)
 *   PATCH  /api/v1/salons/{salon}/services/{service}    → update (change overrides)
 *   DELETE /api/v1/salons/{salon}/services/{service}    → detach (disable service at branch)
 *   GET    /api/v1/salons/{salon}/services/{service}/policy → policy (resolved booking policy)
 */
class SalonServiceController extends Controller
{
    /**
     * List all services available at this branch, with resolved effective pricing.
     */
    /**
     * List all services available at this branch, with resolved effective pricing.
     */
    public function index(Salon $salon, BranchCatalogQuery $catalogQuery): JsonResponse
    {
        $services = $catalogQuery->queryAvailableServices($salon)
            ->with('provider:id,display_name,slug')
            ->get()
            ->map(function (Service $service) use ($salon) {
                $pricing = new PricingEngine($salon, $service);
                $booking = new BookingEngine($salon, $service);
                return [
                    'id'                     => $service->id,
                    'name'                   => $service->name,
                    'description'            => $service->description,
                    'category'               => $service->category,
                    'image_url'              => $service->image_url,
                    'provider_price'         => $service->price,
                    'provider_duration'      => $service->duration,
                    // Resolved effective values for this branch
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
            'salon'    => ['id' => $salon->id, 'name' => $salon->name],
            'services' => $services,
        ]);
    }

    /**
     * Enable a service at this branch (create salon_service pivot record).
     */
    public function attach(Request $request, Salon $salon, Service $service): JsonResponse
    {
        // Verify the service belongs to the same provider as the salon
        if ($service->provider_id !== $salon->provider_id) {
            return response()->json([
                'message' => 'Service does not belong to the same provider as this salon.',
            ], 422);
        }

        // Technically, under open-world inheritance, if no row exists, it's ALREADY assigned.
        // We only enforce "already assigned explicitly" to prevent duplicate identical rows.
        $exists = \Illuminate\Support\Facades\DB::table('salon_service')
            ->where(['salon_id' => $salon->id, 'service_id' => $service->id])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Service overrides already exist for this branch.'], 409);
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

        \Illuminate\Support\Facades\DB::table('salon_service')->updateOrInsert(
            ['salon_id' => $salon->id, 'service_id' => $service->id],
            $validated
        );

        // Fetch the resolved service using the Query to instantiate the Engines
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($salon)->where('services.id', $service->id)->first();
        
        $pricing = new PricingEngine($salon->fresh(), $resolvedService);
        $booking = new BookingEngine($salon->fresh(), $resolvedService);

        return response()->json([
            'message' => 'Service enabled at this branch.',
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
     * Update branch-level overrides for a service.
     */
    public function update(Request $request, Salon $salon, Service $service): JsonResponse
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

        \Illuminate\Support\Facades\DB::table('salon_service')->where([
            'salon_id' => $salon->id,
            'service_id' => $service->id
        ])->update($validated);
        
        // Fetch the resolved service using the Query to instantiate the Engines
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($salon)->where('services.id', $service->id)->first();
        
        $pricing = new PricingEngine($salon->fresh(), $resolvedService);
        $booking = new BookingEngine($salon->fresh(), $resolvedService);

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
     * Disable a service at this branch.
     */
    public function detach(Salon $salon, Service $service): JsonResponse
    {
        \Illuminate\Support\Facades\DB::table('salon_service')->where([
            'salon_id' => $salon->id,
            'service_id' => $service->id
        ])->delete();
        
        return response()->json(['message' => 'Service disabled at this branch.'], 200);
    }

    /**
     * Get the fully resolved booking policy for a service at this branch.
     * This is what the booking UI calls before showing the Book button.
     */
    public function policy(Salon $salon, Service $service): JsonResponse
    {
        if ($service->provider_id !== $salon->provider_id) {
            return response()->json([
                'is_available' => false,
                'reason'       => 'Service is not offered by this provider.',
            ], 200);
        }

        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($salon)->where('services.id', $service->id)->first();
        
        $pricing = new PricingEngine($salon, $resolvedService);
        $booking = new BookingEngine($salon, $resolvedService);

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
