<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BookingConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingConfigurationController extends Controller
{
    private BookingConfigurationService $configService;

    public function __construct(BookingConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Get effective booking configuration for a service at a salon
     */
    public function getConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|uuid|exists:services,id',
            'salon_id' => 'required|uuid|exists:salons,id',
        ]);

        try {
            $config = $this->configService->getConfiguration(
                $validated['service_id'],
                $validated['salon_id']
            );

            return response()->json([
                'configuration' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get provider booking configuration
     */
    public function getProviderConfig(Request $request, string $providerId): JsonResponse
    {
        $config = \App\Models\ProviderBookingConfig::where('provider_id', $providerId)->first();

        if (!$config) {
            return response()->json([
                'configuration' => null,
                'message' => 'No custom configuration set, using defaults',
            ]);
        }

        return response()->json([
            'configuration' => [
                'availability_policy' => $config->availability_policy,
                'assignment_policy' => $config->assignment_policy,
                'payment_policy' => $config->payment_policy,
                'cancellation_policy' => $config->cancellation_policy,
                'approval_policy' => $config->approval_policy,
                'booking_policy' => $config->booking_policy,
            ],
        ]);
    }

    /**
     * Update provider booking configuration
     */
    public function updateProviderConfig(Request $request, string $providerId): JsonResponse
    {
        $validated = $request->validate([
            'availability_policy' => 'nullable|array',
            'assignment_policy' => 'nullable|array',
            'payment_policy' => 'nullable|array',
            'cancellation_policy' => 'nullable|array',
            'approval_policy' => 'nullable|array',
            'booking_policy' => 'nullable|array',
        ]);

        try {
            $config = $this->configService->updateProviderConfig($providerId, $validated);

            return response()->json([
                'message' => 'Provider configuration updated',
                'configuration' => [
                    'availability_policy' => $config->availability_policy,
                    'assignment_policy' => $config->assignment_policy,
                    'payment_policy' => $config->payment_policy,
                    'cancellation_policy' => $config->cancellation_policy,
                    'approval_policy' => $config->approval_policy,
                    'booking_policy' => $config->booking_policy,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update provider configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset provider configuration to defaults
     */
    public function resetProviderConfig(Request $request, string $providerId): JsonResponse
    {
        try {
            \App\Models\ProviderBookingConfig::where('provider_id', $providerId)->delete();

            return response()->json([
                'message' => 'Provider configuration reset to defaults',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset provider configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get salon booking configuration
     */
    public function getSalonConfig(Request $request, string $salonId): JsonResponse
    {
        $config = \App\Models\SalonBookingConfig::where('salon_id', $salonId)->first();

        if (!$config) {
            return response()->json([
                'configuration' => null,
                'message' => 'No custom overrides set, using provider defaults',
            ]);
        }

        return response()->json([
            'configuration' => [
                'availability_policy_override' => $config->availability_policy_override,
                'assignment_policy_override' => $config->assignment_policy_override,
                'payment_policy_override' => $config->payment_policy_override,
                'cancellation_policy_override' => $config->cancellation_policy_override,
                'approval_policy_override' => $config->approval_policy_override,
                'booking_policy_override' => $config->booking_policy_override,
            ],
        ]);
    }

    /**
     * Update salon booking configuration overrides
     */
    public function updateSalonConfig(Request $request, string $salonId): JsonResponse
    {
        $validated = $request->validate([
            'availability_policy_override' => 'nullable|array',
            'assignment_policy_override' => 'nullable|array',
            'payment_policy_override' => 'nullable|array',
            'cancellation_policy_override' => 'nullable|array',
            'approval_policy_override' => 'nullable|array',
            'booking_policy_override' => 'nullable|array',
        ]);

        try {
            $config = $this->configService->updateSalonConfig($salonId, $validated);

            return response()->json([
                'message' => 'Salon configuration updated',
                'configuration' => [
                    'availability_policy_override' => $config->availability_policy_override,
                    'assignment_policy_override' => $config->assignment_policy_override,
                    'payment_policy_override' => $config->payment_policy_override,
                    'cancellation_policy_override' => $config->cancellation_policy_override,
                    'approval_policy_override' => $config->approval_policy_override,
                    'booking_policy_override' => $config->booking_policy_override,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update salon configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset salon configuration to provider defaults
     */
    public function resetSalonConfig(Request $request, string $salonId): JsonResponse
    {
        try {
            $this->configService->resetSalonConfig($salonId);

            return response()->json([
                'message' => 'Salon configuration reset to provider defaults',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset salon configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get service booking configuration
     */
    public function getServiceConfig(Request $request, string $serviceId): JsonResponse
    {
        $config = \App\Models\ServiceBookingConfig::where('service_id', $serviceId)->first();

        if (!$config) {
            return response()->json([
                'configuration' => null,
                'message' => 'No custom overrides set, using salon/provider defaults',
            ]);
        }

        return response()->json([
            'configuration' => [
                'availability_policy_override' => $config->availability_policy_override,
                'assignment_policy_override' => $config->assignment_policy_override,
                'payment_policy_override' => $config->payment_policy_override,
                'cancellation_policy_override' => $config->cancellation_policy_override,
                'approval_policy_override' => $config->approval_policy_override,
                'booking_policy_override' => $config->booking_policy_override,
                'service_requirements' => $config->service_requirements,
            ],
        ]);
    }

    /**
     * Update service booking configuration
     */
    public function updateServiceConfig(Request $request, string $serviceId): JsonResponse
    {
        $validated = $request->validate([
            'availability_policy_override' => 'nullable|array',
            'assignment_policy_override' => 'nullable|array',
            'payment_policy_override' => 'nullable|array',
            'cancellation_policy_override' => 'nullable|array',
            'approval_policy_override' => 'nullable|array',
            'booking_policy_override' => 'nullable|array',
            'service_requirements' => 'nullable|array',
        ]);

        try {
            $requirements = $validated['service_requirements'] ?? [];
            unset($validated['service_requirements']);

            $config = $this->configService->updateServiceConfig($serviceId, $validated, $requirements);

            return response()->json([
                'message' => 'Service configuration updated',
                'configuration' => [
                    'availability_policy_override' => $config->availability_policy_override,
                    'assignment_policy_override' => $config->assignment_policy_override,
                    'payment_policy_override' => $config->payment_policy_override,
                    'cancellation_policy_override' => $config->cancellation_policy_override,
                    'approval_policy_override' => $config->approval_policy_override,
                    'booking_policy_override' => $config->booking_policy_override,
                    'service_requirements' => $config->service_requirements,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update service configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset service configuration to salon/provider defaults
     */
    public function resetServiceConfig(Request $request, string $serviceId): JsonResponse
    {
        try {
            $this->configService->resetServiceConfig($serviceId);

            return response()->json([
                'message' => 'Service configuration reset to defaults',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset service configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
