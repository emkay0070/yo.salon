<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class CustomerPortalService
{
    /**
     * Get customer profile with preferences for a specific salon
     */
    public function getProfile(Customer $customer, string $salonId): array
    {
        $customer->load(['portalAccount']);

        // salonId is a Location ID (from middleware) — derive Provider from it
        $location = \App\Models\Location::find($salonId);
        $provider = $location ? $location->provider()->first() : null;

        if (!$provider) {
            // Fall back: salonId may already be a provider_id in some call paths
            $provider = \App\Models\Provider::find($salonId);
        }

        // Get provider relationship pivot
        $providerPivot = $provider
            ? \DB::table('customer_providers')
                ->where('customer_id', $customer->id)
                ->where('provider_id', $provider->id)
                ->first()
            : null;

        $visits = $providerPivot->visits ?? 0;
        $notes  = $providerPivot->notes ?? null;

        // Get preferences (customer-scoped - no provider/location scoping)
        $preference = CustomerPreference::where('customer_id', $customer->id)->first();

        return [
            'id'    => $customer->id,
            'name'  => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'visits' => $visits,
            'notes'  => $notes,
            'salon'  => $provider ? [
                'id'      => $provider->id,
                'name'    => $provider->display_name ?? $provider->name,
                'slug'    => $provider->slug,
                'logo'    => $provider->logo,
                'phone'   => $location?->phone ?? $provider->phone,
                'email'   => $location?->email ?? $provider->email,
                'address' => $location?->address,
            ] : null,
            'preferences' => $preference ? [
                'preferred_staff_id'       => $preference->preferred_staff_id,
                'notification_preferences' => $preference->notification_preferences,
                'booking_preferences'      => $preference->booking_preferences,
            ] : null,
        ];
    }

    /**
     * Update customer profile for a specific salon
     */
    public function updateProfile(Customer $customer, string $salonId, array $data): array
    {
        // Update customer-level fields
        $customer->update([
            'name'  => $data['name']  ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
        ]);

        // salonId is a Location ID — derive Provider for the pivot update
        $location = \App\Models\Location::find($salonId);
        $providerId = $location?->provider_id;

        // Update notes on the canonical customer_providers pivot
        if ($providerId && isset($data['notes'])) {
            \DB::table('customer_providers')
                ->where('customer_id', $customer->id)
                ->where('provider_id', $providerId)
                ->update(['notes' => $data['notes']]);
        }

        return $this->getProfile($customer, $salonId);
    }

    /**
     * Get customer preferences for a specific salon
     */
    public function getPreferences(Customer $customer, string $salonId): ?CustomerPreference
    {
        // Preferences are customer-scoped - salonId parameter kept for API compatibility but not used
        return CustomerPreference::where('customer_id', $customer->id)->first();
    }

    /**
     * Update customer preferences for a specific salon
     */
    public function updatePreferences(Customer $customer, string $salonId, array $data): CustomerPreference
    {
        $preference = CustomerPreference::where('customer_id', $customer->id)->first();

        if (!$preference) {
            $preference = new CustomerPreference([
                'customer_id' => $customer->id,
            ]);
        }

        $preference->fill([
            'preferred_staff_id' => $data['preferred_staff_id'] ?? $preference->preferred_staff_id,
            'notification_preferences' => $data['notification_preferences'] ?? $preference->notification_preferences,
            'booking_preferences' => $data['booking_preferences'] ?? $preference->booking_preferences,
        ]);

        $preference->save();

        return $preference->fresh();
    }

    /**
     * Get favorite stylist for a specific salon
     */
    public function getFavoriteStylist(Customer $customer, string $salonId): ?array
    {
        $preference = CustomerPreference::where('customer_id', $customer->id)->first();

        if (!$preference || !$preference->preferred_staff_id) {
            return null;
        }

        $staff = $preference->preferredStaff;

        if (!$staff) {
            return null;
        }

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'avatar' => $staff->avatar,
        ];
    }

    /**
     * Set favorite stylist for a specific salon
     */
    public function setFavoriteStylist(Customer $customer, string $salonId, string $staffId): CustomerPreference
    {
        $preference = CustomerPreference::where('customer_id', $customer->id)->first();

        if (!$preference) {
            $preference = new CustomerPreference([
                'customer_id' => $customer->id,
            ]);
        }

        $preference->preferred_staff_id = $staffId;
        $preference->save();

        return $preference->fresh();
    }

    /**
     * Get favorite services for a specific salon
     */
    public function getFavoriteServices(Customer $customer, string $salonId): array
    {
        // Note: CustomerFavoriteService no longer has salon_id column
        // This method needs refactoring - returning empty for now
        // TODO: Determine if favorites should be customer-scoped or provider-scoped
        return [];
    }

    /**
     * Add favorite service for a specific salon
     */
    public function addFavoriteService(Customer $customer, string $salonId, string $serviceId): void
    {
        // Note: CustomerFavoriteService no longer has salon_id column
        // This method needs refactoring - no-op for now
        // TODO: Determine if favorites should be customer-scoped or provider-scoped
    }

    /**
     * Remove favorite service for a specific salon
     */
    public function removeFavoriteService(Customer $customer, string $salonId, string $serviceId): void
    {
        // Note: CustomerFavoriteService no longer has salon_id column
        // This method needs refactoring - no-op for now
        // TODO: Determine if favorites should be customer-scoped or provider-scoped
    }

    /**
     * Get recent visits for a customer at a salon
     */
    public function getRecentVisits(Customer $customer, string $salonId, int $limit = 5): array
    {
        try {
            // salonId is a Location ID; derive provider_id from it
            $location  = \App\Models\Location::find($salonId);
            $providerId = $location?->provider_id;

            $bookings = Booking::where('customer_id', $customer->id)
                ->where('provider_id', $providerId)
                ->where('status', 'completed')
                ->with(['service', 'staff'])
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->limit($limit)
                ->get();

            return $bookings->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'service' => $booking->service ? [
                        'id' => $booking->service->id,
                        'name' => $booking->service->name,
                        'price' => $booking->service->price,
                        'duration' => $booking->service->duration,
                    ] : null,
                    'staff' => $booking->staff ? [
                        'id' => $booking->staff->id,
                        'name' => $booking->staff->name,
                    ] : null,
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('getRecentVisits error', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'salon_id' => $salonId,
            ]);
            return [];
        }
    }

    /**
     * Get last completed booking for quick rebook
     */
    public function getLastBooking(Customer $customer, string $salonId): ?array
    {
        try {
            // salonId is a Location ID; derive provider_id from it
            $location  = \App\Models\Location::find($salonId);
            $providerId = $location?->provider_id;

            $booking = Booking::where('customer_id', $customer->id)
                ->where('provider_id', $providerId)
                ->where('status', 'completed')
                ->with(['service', 'staff'])
                ->orderBy('date', 'desc')
                ->orderBy('time', 'desc')
                ->first();

            if (!$booking) {
                return null;
            }

            return [
                'id' => $booking->id,
                'date' => $booking->date,
                'time' => $booking->time,
                'service' => $booking->service ? [
                    'id' => $booking->service->id,
                    'name' => $booking->service->name,
                    'price' => $booking->service->price,
                    'duration' => $booking->service->duration,
                    'category' => $booking->service->category,
                ] : null,
                'staff' => $booking->staff ? [
                    'id' => $booking->staff->id,
                    'name' => $booking->staff->name,
                ] : null,
            ];
        } catch (\Exception $e) {
            \Log::error('getLastBooking error', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'salon_id' => $salonId,
            ]);
            return null;
        }
    }
}
