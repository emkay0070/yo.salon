<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;

/**
 * LocationServiceCatalogResolver
 * 
 * Handles the logic of determining which services are offered at a specific location.
 * Implements "open-world inheritance" where locations automatically offer all 
 * services defined by their Provider, unless explicitly disabled in the location_service pivot.
 * 
 * This keeps complex LEFT JOIN logic and business rules out of Eloquent relationships.
 */
class LocationServiceCatalogResolver
{
    /**
     * Get an Eloquent query builder for ALL services in the provider's catalog,
     * with the location-specific overrides (price, duration, etc.) joined as attributes.
     */
    public function queryAllServices(Location $location)
    {
        // We use the Provider's services() relationship as the base,
        // which natively filters by provider_id.
        return $location->provider->services()
            ->leftJoin('location_service', function ($join) use ($location) {
                $join->on('services.id', '=', 'location_service.service_id')
                     ->where('location_service.location_id', '=', $location->id);
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
     * Get an Eloquent query builder for ONLY the services available at this location.
     * Inherits provider services unless explicitly disabled (is_available = false).
     */
    public function queryAvailableServices(Location $location)
    {
        return $this->queryAllServices($location)
            ->where(function ($query) {
                // Open-world: if pivot is missing (NULL) or true, it's available.
                $query->whereNull('location_service.is_available')
                      ->orWhere('location_service.is_available', true);
            });
    }
}
