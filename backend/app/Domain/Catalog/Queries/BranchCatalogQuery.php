<?php

namespace App\Domain\Catalog\Queries;



/**
 * BranchCatalogQuery
 * 
 * Read-Model Query for determining which services are offered at a specific branch.
 * Implements "open-world inheritance" where branches automatically offer all 
 * services defined by their Provider, unless explicitly disabled.
 */
class BranchCatalogQuery
{
    /**
     * Get an Eloquent query builder for ALL services in the provider's catalog,
     * with the branch-specific overrides (price, duration, etc.) joined as attributes.
     */
    public function queryAllServices(string $providerId, ?string $locationId = null)
    {
        return \App\Models\Service::where('provider_id', $providerId)
            ->leftJoin('location_service', function ($join) use ($locationId) {
                $join->on('services.id', '=', 'location_service.service_id')
                     ->where('location_service.location_id', '=', $locationId);
            })
            ->select(
                'services.*', 
                'location_service.is_available as pivot_is_available',
                'location_service.online_booking_enabled as pivot_online_booking_enabled',
                'location_service.price_override as pivot_price_override',
                'location_service.duration_override as pivot_duration_override',
                'location_service.buffer_before_override as pivot_buffer_before_override',
                'location_service.buffer_after_override as pivot_buffer_after_override',
                'location_service.min_booking_notice_override as pivot_min_booking_notice_override',
                'location_service.cancellation_cutoff_override as pivot_cancellation_cutoff_override',
                'location_service.deposit_required_override as pivot_deposit_required_override',
                'location_service.notes as pivot_notes'
            );
    }

    /**
     * Get an Eloquent query builder for ONLY the services available at this branch.
     * Inherits provider services unless explicitly disabled (is_available = false).
     */
    public function queryAvailableServices(string $providerId, ?string $locationId = null)
    {
        return $this->queryAllServices($providerId, $locationId)
            ->where(function ($query) {
                $query->whereNull('location_service.is_available')
                      ->orWhere('location_service.is_available', true);
            });
    }
}
