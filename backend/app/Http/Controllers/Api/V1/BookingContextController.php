<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Provider;
use App\Models\Service;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Services\PaymentRulesEngine;
use App\Domain\Payments\Resolvers\EligiblePaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingContextController extends Controller
{
    private PaymentRulesEngine $paymentRulesEngine;
    private EligiblePaymentMethodResolver $paymentMethodResolver;

    public function __construct(
        PaymentRulesEngine $paymentRulesEngine,
        EligiblePaymentMethodResolver $paymentMethodResolver
    ) {
        $this->paymentRulesEngine = $paymentRulesEngine;
        $this->paymentMethodResolver = $paymentMethodResolver;
    }

    public function paymentInstructions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
            'service_id' => 'required|uuid|exists:services,id',
        ]);

        $locationId = $validated['location_id'] ?? null;
        $providerId = $validated['provider_id'] ?? null;
        $serviceId = $validated['service_id'];

        // ── Provider-level gate: check the provider is active and accepting bookings ──
        $resolvedProviderId = $providerId;
        if ($locationId && !$providerId) {
            $loc = Location::find($locationId);
            $resolvedProviderId = $loc?->provider_id;
        }
        if ($resolvedProviderId) {
            $provider = Provider::find($resolvedProviderId);
            if ($provider && (!$provider->active || in_array($provider->status, ['inactive', 'suspended'], true))) {
                return response()->json([
                    'message' => ($provider->display_name ?? 'This provider') . ' is not currently accepting bookings.',
                    'provider_name' => $provider->display_name,
                    'provider_status' => $provider->status,
                    'code' => 'PROVIDER_DISABLED',
                ], 422);
            }
        }

        // For location-based context (normal providers with branches)
        if ($locationId) {
            $location = Location::findOrFail($locationId);
            $catalogQuery = new BranchCatalogQuery();
            $resolvedService = $catalogQuery->queryAllServices($location)->where('services.id', $serviceId)->first();

            if (!$resolvedService) {
                return response()->json(['message' => 'Service not found in branch catalog.'], 404);
            }

            $servicePrice = $resolvedService->price;

            // Generate instructions DTO
            $instruction = $this->paymentRulesEngine->generatePaymentInstructions($serviceId, $locationId, $servicePrice);

            // Resolve eligible methods
            try {
                $instruction->eligibleMethods = $this->paymentMethodResolver->resolve($instruction, $locationId);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Configuration Invalid: ' . $e->getMessage()
                ], 422);
            }

            return response()->json($instruction);
        }

        // For provider-level context (independent specialists without location)
        if ($providerId) {
            $service = Service::findOrFail($serviceId);
            $servicePrice = $service->price;

            // Generate instructions DTO with provider context (no location)
            $instruction = $this->paymentRulesEngine->generatePaymentInstructions($serviceId, $providerId, null, $servicePrice);

            // Resolve eligible methods for provider (no location)
            try {
                $instruction->eligibleMethods = $this->paymentMethodResolver->resolveForProvider($instruction, $providerId);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Configuration Invalid: ' . $e->getMessage()
                ], 422);
            }

            return response()->json($instruction);
        }

        return response()->json(['message' => 'Either location_id or provider_id is required'], 400);
    }
}
