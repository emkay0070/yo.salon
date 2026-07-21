<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ServicePackageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServicePackageController extends Controller
{
    protected ServicePackageService $packageService;

    public function __construct(ServicePackageService $packageService)
    {
        $this->packageService = $packageService;
    }

    /**
     * Get active packages for the salon
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');

        try {
            $packages = $this->packageService->getActivePackages($salonId);

            return response()->json([
                'packages' => $packages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get packages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new package (admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salon_id' => 'required|uuid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'services_included' => 'required|integer|min:1',
            'service_ids' => 'nullable|array',
            'validity_days' => 'nullable|integer|min:1',
            'image' => 'nullable|string',
        ]);

        try {
            $package = $this->packageService->createPackage($validated);

            return response()->json([
                'message' => 'Package created successfully',
                'package' => $package,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a package (admin)
     */
    public function update(Request $request, string $packageId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'services_included' => 'sometimes|integer|min:1',
            'service_ids' => 'nullable|array',
            'validity_days' => 'nullable|integer|min:1',
            'active' => 'sometimes|boolean',
            'image' => 'nullable|string',
        ]);

        try {
            $package = $this->packageService->updatePackage($packageId, $validated);

            return response()->json([
                'message' => 'Package updated successfully',
                'package' => $package,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a package (admin)
     */
    public function destroy(string $packageId): JsonResponse
    {
        try {
            $this->packageService->deletePackage($packageId);

            return response()->json([
                'message' => 'Package deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Purchase a package
     */
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => 'required|uuid',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $result = $this->packageService->purchasePackage($customerId, $salonId, $validated['package_id']);

            return response()->json([
                'message' => 'Package purchased successfully',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to purchase package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer's packages
     */
    public function customerPackages(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $customerId = $request->attributes->get('customer_id');

        try {
            $packages = $this->packageService->getCustomerPackages($customerId, $salonId);

            return response()->json([
                'packages' => $packages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get customer packages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
