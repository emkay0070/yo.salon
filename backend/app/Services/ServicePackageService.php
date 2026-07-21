<?php

namespace App\Services;

use App\Models\ServicePackage;
use App\Models\Customer;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;

class ServicePackageService
{
    /**
     * Get active packages for a salon
     */
    public function getActivePackages(string $salonId): array
    {
        $packages = ServicePackage::getActiveForSalon($salonId);

        return $packages->map(function ($package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => $package->price,
                'services_included' => $package->services_included,
                'service_ids' => $package->service_ids,
                'validity_days' => $package->validity_days,
                'image' => $package->image,
            ];
        })->toArray();
    }

    /**
     * Create a new service package
     */
    public function createPackage(array $data): ServicePackage
    {
        return DB::transaction(function () use ($data) {
            return ServicePackage::create($data);
        });
    }

    /**
     * Update a service package
     */
    public function updatePackage(string $packageId, array $data): ServicePackage
    {
        $package = ServicePackage::findOrFail($packageId);
        $package->update($data);
        return $package->fresh();
    }

    /**
     * Delete a service package
     */
    public function deletePackage(string $packageId): void
    {
        $package = ServicePackage::findOrFail($packageId);
        $package->delete();
    }

    /**
     * Purchase a package for a customer
     */
    public function purchasePackage(string $customerId, string $salonId, string $packageId): array
    {
        return DB::transaction(function () use ($customerId, $salonId, $packageId) {
            $package = ServicePackage::findOrFail($packageId);
            
            // Create customer package record
            $customerPackage = CustomerPackage::create([
                'customer_id' => $customerId,
                'salon_id' => $salonId,
                'package_id' => $packageId,
                'services_remaining' => $package->services_included,
                'expires_at' => $package->validity_days 
                    ? now()->addDays($package->validity_days) 
                    : null,
            ]);

            // Deduct from wallet or process payment
            // This would integrate with payment processing

            return [
                'package' => $package,
                'customer_package' => $customerPackage,
            ];
        });
    }

    /**
     * Use a service from a customer's package
     */
    public function useServiceFromPackage(string $customerPackageId): bool
    {
        return DB::transaction(function () use ($customerPackageId) {
            $customerPackage = CustomerPackage::findOrFail($customerPackageId);

            if ($customerPackage->services_remaining <= 0) {
                throw new \Exception('No services remaining in package');
            }

            if ($customerPackage->expires_at && $customerPackage->expires_at->isPast()) {
                throw new \Exception('Package has expired');
            }

            $customerPackage->decrement('services_remaining');

            return true;
        });
    }

    /**
     * Get customer's active packages
     */
    public function getCustomerPackages(string $customerId, string $salonId): array
    {
        $packages = CustomerPackage::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('services_remaining', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('package')
            ->get();

        return $packages->map(function ($customerPackage) {
            return [
                'id' => $customerPackage->id,
                'package_name' => $customerPackage->package->name,
                'services_remaining' => $customerPackage->services_remaining,
                'expires_at' => $customerPackage->expires_at?->toIso8601String(),
                'purchased_at' => $customerPackage->created_at->toIso8601String(),
            ];
        })->toArray();
    }
}
