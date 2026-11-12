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
 * Principle: A Customer is created or resolved through a business interaction with a Provider—not by authentication alone.
 *
 * Architecture note: All former $salonId parameters in this class now represent Provider IDs,
 * as the canonical customer-tenant relationship is Customer → Provider (customer_providers pivot).
 * Location context is tracked on the Booking itself, not on the customer relationship.
 */
class CustomerService
{
    private CustomerResolver $customerResolver;

    public function __construct(CustomerResolver $customerResolver)
    {
        $this->customerResolver = $customerResolver;
    }

    /**
     * Create a new customer with a Provider relationship.
     *
     * This should only be called as part of a business workflow (booking, walk-in, reception, etc.)
     *
     * @param array  $data       Customer data (name, phone, email)
     * @param string $providerId The Provider establishing the relationship
     * @return Customer
     */
    public function createWithSalon(array $data, string $providerId): Customer
    {
        return DB::transaction(function () use ($data, $providerId) {
            $customer = Customer::create([
                'name'  => $data['name'] ?? 'New Customer',
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
            ]);

            // Upsert into customer_providers (idempotent: safe if already exists)
            DB::statement(
                'INSERT INTO customer_providers (id, customer_id, provider_id, visits, joined_at, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?)
                 ON CONFLICT (customer_id, provider_id) DO NOTHING',
                [(string) Str::orderedUuid(), $customer->id, $providerId, now(), now(), now()]
            );

            return $customer->fresh();
        });
    }

    /**
     * Resolve or create customer for a business workflow.
     *
     * This is the main entry point for business workflows to get or create a customer.
     * Uses CustomerResolver to prevent duplicates.
     *
     * @param array  $data              Customer data (name, phone, email)
     * @param string $salonId           The Provider ID establishing the relationship
     * @param bool   $createIfNotFound  Whether to create customer if not found
     * @return array ['customer' => Customer, 'is_new' => bool, 'is_new_salon_relationship' => bool]
     */
    public function resolveOrCreateForBusiness(array $data, string $salonId, bool $createIfNotFound = true): array
    {
        return $this->customerResolver->resolveOrCreateForSalon($data, $salonId, $createIfNotFound);
    }

    /**
     * Update customer information.
     *
     * @param Customer $customer
     * @param array    $data
     * @return Customer
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name'  => $data['name']  ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
        ]);

        return $customer->fresh();
    }

    /**
     * Add a Provider relationship to an existing customer.
     *
     * Idempotent — safe to call even if the relationship already exists.
     *
     * @param Customer $customer
     * @param string   $providerId  Provider ID
     * @return Customer
     */
    public function addProviderRelationship(Customer $customer, string $providerId): Customer
    {
        $exists = DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $providerId)
            ->exists();

        if (!$exists) {
            DB::statement(
                'INSERT INTO customer_providers (id, customer_id, provider_id, visits, joined_at, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?)
                 ON CONFLICT (customer_id, provider_id) DO NOTHING',
                [(string) Str::orderedUuid(), $customer->id, $providerId, now(), now(), now()]
            );
        }

        return $customer->fresh();
    }

    /**
     * Increment visit count for a Provider relationship.
     *
     * @param Customer $customer
     * @param string   $providerId  Provider ID (legacy param name kept for call-site compat)
     * @return Customer
     */
    public function incrementVisit(Customer $customer, string $providerId): Customer
    {
        DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $providerId)
            ->increment('visits');

        return $customer->fresh();
    }

    /**
     * Update notes for a Provider relationship.
     *
     * @param Customer $customer
     * @param string   $providerId  Provider ID (legacy param name kept for call-site compat)
     * @param string   $notes
     * @return Customer
     */
    public function updateSalonNotes(Customer $customer, string $providerId, string $notes): Customer
    {
        DB::table('customer_providers')
            ->where('customer_id', $customer->id)
            ->where('provider_id', $providerId)
            ->update(['notes' => $notes, 'updated_at' => now()]);

        return $customer->fresh();
    }
}
