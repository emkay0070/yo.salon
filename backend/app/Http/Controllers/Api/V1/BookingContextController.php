<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salon;
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
            'salon_id' => 'required|uuid|exists:salons,id',
            'service_id' => 'required|uuid|exists:services,id',
        ]);

        $salonId = $validated['salon_id'];
        $serviceId = $validated['service_id'];

        // Securely resolve the price from the backend
        $salon = Salon::findOrFail($salonId);
        $catalogQuery = new BranchCatalogQuery();
        $resolvedService = $catalogQuery->queryAllServices($salon)->where('services.id', $serviceId)->first();

        if (!$resolvedService) {
            return response()->json(['message' => 'Service not found in branch catalog.'], 404);
        }

        $servicePrice = $resolvedService->price;

        // Generate instructions DTO
        $instruction = $this->paymentRulesEngine->generatePaymentInstructions($serviceId, $salonId, $servicePrice);

        // Resolve eligible methods
        try {
            $instruction->eligibleMethods = $this->paymentMethodResolver->resolve($instruction, $salonId);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Configuration Invalid: ' . $e->getMessage()
            ], 422);
        }

        return response()->json($instruction);
    }
}
