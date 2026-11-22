<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * CustomerPolicy
 *
 * Enforces Provider-scoped customer authorization.
 *
 * A Customer is a globally shared identity record. Operational ownership
 * is established through the `customer_providers` pivot table. A Provider
 * may only view, modify or perform operations on a Customer when an active
 * pivot entry exists for that Provider.
 *
 * Authorization always uses the server-side Provider context from request
 * attributes (set by the `provider.context` middleware). Client-supplied
 * provider IDs are NEVER trusted.
 *
 * Denies access if:
 * - The authenticated principal is not a User (e.g. PortalAccount or SpecialistAccount)
 * - Provider context is missing or null
 * - The User has no active membership in the resolved Provider
 * - The Customer is not associated with the active Provider in customer_providers
 *
 * FIND-CRIT-03 remediation.
 */
class CustomerPolicy
{
    use HandlesAuthorization;

    /**
     * Resolve the active provider ID from the current HTTP request attributes.
     * Never trusts a provider ID supplied in the request body or query string.
     */
    private function resolveProviderId(): ?string
    {
        return request()->attributes->get('provider_id');
    }

    /**
     * Verify that the customer is associated with the active Provider.
     */
    private function customerBelongsToProvider(Customer $customer, string $providerId): bool
    {
        return $customer->providers()
            ->where('providers.id', $providerId)
            ->exists();
    }

    /**
     * Core authorization check for staff customer access.
     */
    private function authorizeCustomerAccess($user, Customer $customer): bool
    {
        // 1. Principal must be a valid User model (PortalAccount and SpecialistAccount cannot gain staff customer privileges)
        if (!$user instanceof User) {
            return false;
        }

        // 2. Resolve active provider from server-side context only
        $providerId = $this->resolveProviderId();
        if (!$providerId) {
            return false;
        }

        // 3. User must have membership in this active Provider
        $isMember = $user->providers()
            ->where('providers.id', $providerId)
            ->exists();

        if (!$isMember) {
            return false;
        }

        // 4. Customer must be associated with the active Provider in customer_providers
        return $this->customerBelongsToProvider($customer, $providerId);
    }

    /**
     * view: Provider staff may view a customer only if associated via customer_providers.
     */
    public function view($user, Customer $customer): bool
    {
        return $this->authorizeCustomerAccess($user, $customer);
    }

    /**
     * update: Provider staff may update a customer only if associated via customer_providers.
     */
    public function update($user, Customer $customer): bool
    {
        return $this->authorizeCustomerAccess($user, $customer);
    }

    /**
     * detach: Provider staff may remove their Provider's association with a customer.
     *
     * This is the only safe "delete" operation permitted per Provider:
     * it removes the customer_providers pivot entry for this Provider only.
     * It never deletes the global customer identity record, which may be shared
     * across multiple Providers.
     *
     * Global deletion requires a separate domain decision and is explicitly denied
     * in CustomerController::destroy().
     */
    public function detach($user, Customer $customer): bool
    {
        return $this->authorizeCustomerAccess($user, $customer);
    }
}

