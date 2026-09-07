<?php

namespace App\Services;

use App\Models\Salon;
use Illuminate\Database\Eloquent\Builder;

/**
 * BranchServiceCatalogResolver
 * 
 * Handles the logic of determining which services are offered at a specific branch.
 * Implements "open-world inheritance" where branches automatically offer all 
 * services defined by their Provider, unless explicitly disabled in the salon_service pivot.
 * 
 * This keeps complex LEFT JOIN logic and business rules out of Eloquent relationships.
 */
class BranchServiceCatalogResolver
{
    /**
     * Get an Eloquent query builder for ALL services in the provider's catalog,
     * with the branch-specific overrides (price, duration, etc.) joined as attributes.
     */
    public function queryAllServices(Salon $salon)
    {
        // We use the Provider's services() relationship as the base,
        // which natively filters by provider_id.
        return $salon->provider->services()
            ->leftJoin('salon_service', function ($join) use ($salon) {
                $join->on('services.id', '=', 'salon_service.service_id')
                     ->where('salon_service.salon_id', '=', $salon->id);
            })
            ->select(
                'services.*', 
                'salon_service.is_available as pivot_is_available',
                'salon_service.online_booking_enabled as pivot_online_booking_enabled',
                'salon_service.price_override as pivot_price_override',
                'salon_service.duration_override as pivot_duration_override',
                'salon_service.buffer_before_override as pivot_buffer_before_override',
                'salon_service.buffer_after_override as pivot_buffer_after_override',
                'salon_service.min_booking_notice_override as pivot_min_booking_notice_override',
                'salon_service.cancellation_cutoff_override as pivot_cancellation_cutoff_override',
                'salon_service.deposit_required_override as pivot_deposit_required_override',
                'salon_service.notes as pivot_notes'
            );
    }

    /**
     * Get an Eloquent query builder for ONLY the services available at this branch.
     * Inherits provider services unless explicitly disabled (is_available = false).
     */
    public function queryAvailableServices(Salon $salon)
    {
        return $this->queryAllServices($salon)
            ->where(function ($query) {
                // Open-world: if pivot is missing (NULL) or true, it's available.
                $query->whereNull('salon_service.is_available')
                      ->orWhere('salon_service.is_available', true);
            });
    }
}
