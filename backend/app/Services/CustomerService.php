<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CustomerService
 * 
 * Responsible for customer creation within a business context.
 * This service should only be called by business workflows (BookingService, ReceptionService, etc.)
 * Never by authentication services alone.
 * 
 * Principle: A Customer is created or resolved through a business interaction with a salon—not by authentication alone.
 */
class CustomerService
{
    private CustomerResolver $customerResolver;

    public function __construct(CustomerResolver $customerResolver)
    {
        $this->customerResolver = $customerResolver;
    }

    /**
     * Create a new customer with a salon relationship
     * 
     * This should only be called as part of a business workflow (booking, walk-in, reception, etc.)
     * 
     * @param array $data Customer data (name, phone, email)
     * @param string $salonId The salon establishing the relationship
     * @return Customer
     */
    public function createWithSalon(array $data, string $salonId): Customer
    {
        return DB::transaction(function () use ($data, $salonId) {
            $customer = Customer::create([
                'name' => $data['name'] ?? 'New Customer',
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
            ]);

            $customer->salons()->attach($salonId, [
                'id' => (string) Str::uuid(),
                'visits' => 0,
                'joined_at' => now(),
            ]);

            return $customer->fresh();
        });
    }

    /**
     * Resolve or create customer for a business workflow
     * 
     * This is the main entry point for business workflows to get or create a customer.
     * Uses CustomerResolver to prevent duplicates.
     * 
     * @param array $data Customer data (name, phone, email)
     * @param string $salonId The salon establishing the relationship
     * @param bool $createIfNotFound Whether to create customer if not found
     * @return array ['customer' => Customer, 'is_new' => bool, 'is_new_salon_relationship' => bool]
     */
    public function resolveOrCreateForBusiness(array $data, string $salonId, bool $createIfNotFound = true): array
    {
        return $this->customerResolver->resolveOrCreateForSalon($data, $salonId, $createIfNotFound);
    }

    /**
     * Update customer information
     * 
     * @param Customer $customer
     * @param array $data
     * @return Customer
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name' => $data['name'] ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
        ]);

        return $customer->fresh();
    }

    /**
     * Add salon relationship to existing customer
     * 
     * @param Customer $customer
     * @param string $salonId
     * @return Customer
     */
    public function addSalonRelationship(Customer $customer, string $salonId): Customer
    {
        if (!$customer->salons()->where('salon_id', $salonId)->exists()) {
            $customer->salons()->attach($salonId, [
                'id' => (string) Str::uuid(),
                'visits' => 0,
                'joined_at' => now(),
            ]);
        }

        return $customer->fresh();
    }

    /**
     * Increment visit count for a salon relationship
     * 
     * @param Customer $customer
     * @param string $salonId
     * @return Customer
     */
    public function incrementVisit(Customer $customer, string $salonId): Customer
    {
        $relationship = $customer->salons()->where('salon_id', $salonId)->first();
        
        if ($relationship) {
            $customer->salons()->updateExistingPivot($salonId, [
                'visits' => $relationship->pivot->visits + 1,
            ]);
        }

        return $customer->fresh();
    }

    /**
     * Update notes for a salon relationship
     * 
     * @param Customer $customer
     * @param string $salonId
     * @param string $notes
     * @return Customer
     */
    public function updateSalonNotes(Customer $customer, string $salonId, string $notes): Customer
    {
        if ($customer->salons()->where('salon_id', $salonId)->exists()) {
            $customer->salons()->updateExistingPivot($salonId, [
                'notes' => $notes,
            ]);
        }

        return $customer->fresh();
    }
}
