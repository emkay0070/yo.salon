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

        // Get salon relationship
        $salonRelationship = $customer->salons()->where('salon_id', $salonId)->first();
        if (!$salonRelationship) {
            throw new \Exception('Customer does not have a relationship with this salon');
        }

        $salon = $salonRelationship;
        $visits = $salonRelationship->pivot->visits;
        $notes = $salonRelationship->pivot->notes;

        // Get salon-specific preferences
        $preference = CustomerPreference::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'visits' => $visits,
            'notes' => $notes,
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'slug' => $salon->slug,
                'logo' => $salon->logo,
                'phone' => $salon->phone,
                'email' => $salon->email,
                'address' => $salon->address,
                'opening_hours' => $salon->opening_hours,
            ],
            'preferences' => $preference ? [
                'preferred_staff_id' => $preference->preferred_staff_id,
                'notification_preferences' => $preference->notification_preferences,
                'booking_preferences' => $preference->booking_preferences,
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
            'name' => $data['name'] ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
        ]);

        // Update salon-specific fields in pivot
        $customer->salons()->updateExistingPivot($salonId, [
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->getProfile($customer, $salonId);
    }

    /**
     * Get customer preferences for a specific salon
     */
    public function getPreferences(Customer $customer, string $salonId): ?CustomerPreference
    {
        return CustomerPreference::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();
    }

    /**
     * Update customer preferences for a specific salon
     */
    public function updatePreferences(Customer $customer, string $salonId, array $data): CustomerPreference
    {
        $preference = CustomerPreference::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();

        if (!$preference) {
            $preference = new CustomerPreference([
                'customer_id' => $customer->id,
                'salon_id' => $salonId,
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
        $preference = CustomerPreference::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();

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
            'specializations' => $staff->specializations,
            'avatar' => $staff->avatar,
        ];
    }

    /**
     * Set favorite stylist for a specific salon
     */
    public function setFavoriteStylist(Customer $customer, string $salonId, string $staffId): CustomerPreference
    {
        $preference = CustomerPreference::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();

        if (!$preference) {
            $preference = new CustomerPreference([
                'customer_id' => $customer->id,
                'salon_id' => $salonId,
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
        $favoriteServices = $customer->favoriteServices()
            ->where('salon_id', $salonId)
            ->with('service')
            ->orderBy('added_at', 'desc')
            ->get()
            ->map(function ($favorite) {
                return [
                    'id' => $favorite->service->id,
                    'name' => $favorite->service->name,
                    'description' => $favorite->service->description,
                    'price' => $favorite->service->price,
                    'duration' => $favorite->service->duration,
                    'category' => $favorite->service->category,
                    'image' => $favorite->service->image,
                    'added_at' => $favorite->added_at,
                ];
            })
            ->toArray();

        return $favoriteServices;
    }

    /**
     * Add favorite service for a specific salon
     */
    public function addFavoriteService(Customer $customer, string $salonId, string $serviceId): void
    {
        $customer->favoriteServices()->firstOrCreate([
            'salon_id' => $salonId,
            'service_id' => $serviceId,
        ]);
    }

    /**
     * Remove favorite service for a specific salon
     */
    public function removeFavoriteService(Customer $customer, string $salonId, string $serviceId): void
    {
        $customer->favoriteServices()
            ->where('salon_id', $salonId)
            ->where('service_id', $serviceId)
            ->delete();
    }

    /**
     * Get recent visits for a customer at a salon
     */
    public function getRecentVisits(Customer $customer, string $salonId, int $limit = 5): array
    {
        $bookings = Booking::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
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
                'service' => [
                    'id' => $booking->service->id,
                    'name' => $booking->service->name,
                    'price' => $booking->service->price,
                    'duration' => $booking->service->duration,
                ],
                'staff' => $booking->staff ? [
                    'id' => $booking->staff->id,
                    'name' => $booking->staff->name,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get last completed booking for quick rebook
     */
    public function getLastBooking(Customer $customer, string $salonId): ?array
    {
        $booking = Booking::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
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
            'service' => [
                'id' => $booking->service->id,
                'name' => $booking->service->name,
                'price' => $booking->service->price,
                'duration' => $booking->service->duration,
                'category' => $booking->service->category,
            ],
            'staff' => $booking->staff ? [
                'id' => $booking->staff->id,
                'name' => $booking->staff->name,
            ] : null,
        ];
    }
}
