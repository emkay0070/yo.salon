<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CustomerResolver
 * 
 * Responsible for resolving existing customers and preventing duplicates.
 * Every business workflow should use this service to find or resolve customers.
 * 
 * Principle: Authentication identifies people. Business workflows establish relationships.
 * This service handles customer identity within a salon context.
 */
class CustomerResolver
{
    /**
     * Resolve customer by contact information (phone or email)
     * 
     * @param string $contact Phone number or email
     * @return Customer|null
     */
    public function findByContact(string $contact): ?Customer
    {
        return Customer::where('phone', $contact)
            ->orWhere('email', $contact)
            ->first();
    }

    /**
     * Resolve customer by phone
     * 
     * @param string $phone
     * @return Customer|null
     */
    public function findByPhone(string $phone): ?Customer
    {
        return Customer::where('phone', $phone)->first();
    }

    /**
     * Resolve customer by email
     * 
     * @param string $email
     * @return Customer|null
     */
    public function findByEmail(string $email): ?Customer
    {
        return Customer::where('email', $email)->first();
    }

    /**
     * Resolve customer for a specific provider
     * Returns customer only if they have a relationship with the provider
     * 
     * @param string $contact Phone or email
     * @param string $providerId
     * @return Customer|null
     */
    public function findByContactForProvider(string $contact, string $providerId): ?Customer
    {
        $customer = $this->findByContact($contact);
        
        if (!$customer) {
            return null;
        }

        // Check if customer has relationship with this provider
        if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
            return null;
        }

        return $customer;
    }

    /**
     * Resolve or create customer within a business context
     * 
     * This method should only be called by business workflows (BookingService, ReceptionService, etc.)
     * Never by authentication services alone.
     * 
     * @param array $data Customer data (name, phone, email)
     * @param string $providerId The provider establishing the relationship
     * @param bool $createIfNotFound Whether to create customer if not found
     * @return array ['customer' => Customer, 'is_new' => bool, 'is_new_provider_relationship' => bool]
     * @throws \Exception if customer not found and createIfNotFound is false
     */
    public function resolveOrCreateForProvider(array $data, string $providerId, bool $createIfNotFound = true): array
    {
        return DB::transaction(function () use ($data, $providerId, $createIfNotFound) {
            $phone = $data['phone'];
            $email = $data['email'] ?? null;
            
            // Try to find existing customer by phone (primary identifier)
            $customer = $this->findByPhone($phone);
            
            // If not found by phone, try email
            if (!$customer && $email) {
                $customer = $this->findByEmail($email);
            }
            
            if ($customer) {
                // Customer exists, check provider relationship
                $hasProviderRelationship = $customer->providers()->where('provider_id', $providerId)->exists();
                
                if (!$hasProviderRelationship) {
                    // Create new provider relationship for existing customer
                    $customer->providers()->attach($providerId, [
                        'id' => (string) Str::uuid(),
                        'visits' => 0,
                        'joined_at' => now(),
                    ]);
                    
                    return [
                        'customer' => $customer->fresh(),
                        'is_new' => false,
                        'is_new_provider_relationship' => true,
                    ];
                }
                
                return [
                    'customer' => $customer,
                    'is_new' => false,
                    'is_new_provider_relationship' => false,
                ];
            }
            
            // Customer doesn't exist
            if (!$createIfNotFound) {
                throw new \Exception('Customer not found. Please complete a booking or visit first.');
            }
            
            // Create new customer with provider relationship
            $customer = Customer::create([
                'name' => $data['name'] ?? 'New Customer',
                'phone' => $phone,
                'email' => $email,
            ]);
            
            $customer->providers()->attach($providerId, [
                'id' => (string) Str::uuid(),
                'visits' => 0,
                'joined_at' => now(),
            ]);
            
            return [
                'customer' => $customer->fresh(),
                'is_new' => true,
                'is_new_provider_relationship' => true,
            ];
        });
    }

    /**
     * Merge duplicate customer records
     * 
     * This is a safety mechanism for future use if duplicates somehow get created
     * 
     * @param Customer $primary The customer to keep
     * @param Customer $duplicate The customer to merge into primary
     * @return Customer The merged customer
     */
    public function mergeCustomers(Customer $primary, Customer $duplicate): Customer
    {
        return DB::transaction(function () use ($primary, $duplicate) {
            // Transfer bookings
            $duplicate->bookings()->update(['customer_id' => $primary->id]);
            
            // Transfer provider relationships
            foreach ($duplicate->providers as $provider) {
                $pivot = $duplicate->providers()->where('provider_id', $provider->id)->first()->pivot;
                
                // Check if primary already has this provider
                if (!$primary->providers()->where('provider_id', $provider->id)->exists()) {
                    $primary->providers()->attach($provider->id, [
                        'id' => (string) Str::uuid(),
                        'visits' => $pivot->visits,
                        'joined_at' => $pivot->joined_at,
                    ]);
                } else {
                    // Merge visit counts
                    $existingPivot = $primary->providers()->where('provider_id', $provider->id)->first()->pivot;
                    $primary->providers()->updateExistingPivot($provider->id, [
                        'visits' => $existingPivot->visits + $pivot->visits,
                    ]);
                }
            }
            
            // Transfer preferences
            $duplicate->preference()->update(['customer_id' => $primary->id]);
            
            // Transfer favorite services
            $duplicate->favoriteServices()->update(['customer_id' => $primary->id]);
            
            // Transfer portal account if duplicate has one and primary doesn't
            if ($duplicate->portalAccount && !$primary->portalAccount) {
                $duplicate->portalAccount()->update(['customer_id' => $primary->id]);
            }
            
            // Delete duplicate
            $duplicate->delete();
            
            return $primary->fresh();
        });
    }
}
