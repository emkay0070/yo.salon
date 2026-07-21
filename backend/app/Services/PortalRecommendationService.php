<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Service;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class PortalRecommendationService
{
    /**
     * Get recommended services for a customer at a specific salon
     * Based on visit history, favorite stylist, preferred category, seasonal campaigns
     */
    public function getRecommendedServices(Customer $customer, string $salonId, int $limit = 6): array
    {
        $customerId = $customer->id;

        // Get customer's booking history to find frequently booked services at this salon
        $frequentlyBookedServiceIds = Booking::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->where('status', 'completed')
            ->select('service_id', DB::raw('COUNT(*) as count'))
            ->groupBy('service_id')
            ->orderBy('count', 'desc')
            ->limit(3)
            ->pluck('service_id')
            ->toArray();

        // Get customer's preferred stylist for this salon
        $preferredStaffId = \App\Models\CustomerPreference::where('customer_id', $customerId)
            ->where('salon_id', $salonId)
            ->first()?->preferred_staff_id;

        // Get services from the same category as frequently booked services
        $categoryIds = [];
        if (!empty($frequentlyBookedServiceIds)) {
            $categoryIds = Service::whereIn('id', $frequentlyBookedServiceIds)
                ->where('salon_id', $salonId)
                ->pluck('category')
                ->unique()
                ->toArray();
        }

        // Build query for recommended services
        $query = Service::where('salon_id', $salonId)
            ->where('active', true);

        // Prioritize services from same category as frequently booked
        if (!empty($categoryIds)) {
            $query->orderByRaw('FIELD(category, ' . implode(',', array_fill(0, count($categoryIds), '?')) . ') DESC', $categoryIds);
        }

        // If customer has preferred stylist, prioritize services they offer
        if ($preferredStaffId) {
            // This would require a staff_services pivot table
            // For now, we'll just get active services
        }

        // Exclude already frequently booked services to suggest new ones
        if (!empty($frequentlyBookedServiceIds)) {
            $query->whereNotIn('id', $frequentlyBookedServiceIds);
        }

        $services = $query->limit($limit)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'duration' => $service->duration,
                    'category' => $service->category,
                    'image' => $service->image,
                ];
            })
            ->toArray();

        return $services;
    }

    /**
     * Get trending services
     * Based on booking count in the last 30 days
     */
    public function getTrendingServices(string $salonId, int $limit = 6): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $trendingServiceIds = Booking::where('salon_id', $salonId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', '!=', 'cancelled')
            ->select('service_id', DB::raw('COUNT(*) as count'))
            ->groupBy('service_id')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->pluck('service_id')
            ->toArray();

        if (empty($trendingServiceIds)) {
            // Fallback to active services
            return Service::where('salon_id', $salonId)
                ->where('active', true)
                ->limit($limit)
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'price' => $service->price,
                        'duration' => $service->duration,
                        'category' => $service->category,
                        'image' => $service->image,
                    ];
                })
                ->toArray();
        }

        return Service::whereIn('id', $trendingServiceIds)
            ->where('salon_id', $salonId)
            ->where('active', true)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'duration' => $service->duration,
                    'category' => $service->category,
                    'image' => $service->image,
                ];
            })
            ->toArray();
    }

    /**
     * Get popular services
     * Based on all-time booking count
     */
    public function getPopularServices(string $salonId, int $limit = 6): array
    {
        $popularServiceIds = Booking::where('salon_id', $salonId)
            ->where('status', '!=', 'cancelled')
            ->select('service_id', DB::raw('COUNT(*) as count'))
            ->groupBy('service_id')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->pluck('service_id')
            ->toArray();

        if (empty($popularServiceIds)) {
            // Fallback to active services
            return Service::where('salon_id', $salonId)
                ->where('active', true)
                ->limit($limit)
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'price' => $service->price,
                        'duration' => $service->duration,
                        'category' => $service->category,
                        'image' => $service->image,
                    ];
                })
                ->toArray();
        }

        return Service::whereIn('id', $popularServiceIds)
            ->where('salon_id', $salonId)
            ->where('active', true)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'duration' => $service->duration,
                    'category' => $service->category,
                    'image' => $service->image,
                ];
            })
            ->toArray();
    }

    /**
     * Get seasonal services
     * Based on current season/month
     */
    public function getSeasonalServices(string $salonId, int $limit = 6): array
    {
        // Simple seasonal logic - in production, this would be more sophisticated
        $currentMonth = now()->month;

        // Define seasonal categories based on month
        $seasonalCategories = match($currentMonth) {
            12, 1, 2 => ['Holiday', 'Winter Care'], // Winter
            3, 4, 5 => ['Spring', 'Refresh'], // Spring
            6, 7, 8 => ['Summer', 'Beach'], // Summer
            9, 10, 11 => ['Fall', 'Autumn'], // Fall
            default => [],
        };

        if (empty($seasonalCategories)) {
            return [];
        }

        return Service::where('salon_id', $salonId)
            ->where('active', true)
            ->where(function ($query) use ($seasonalCategories) {
                foreach ($seasonalCategories as $category) {
                    $query->orWhere('category', 'like', "%{$category}%");
                }
            })
            ->limit($limit)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'duration' => $service->duration,
                    'category' => $service->category,
                    'image' => $service->image,
                ];
            })
            ->toArray();
    }

    /**
     * Get recently added services
     */
    public function getRecentlyAddedServices(string $salonId, int $limit = 6): array
    {
        return Service::where('salon_id', $salonId)
            ->where('active', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'duration' => $service->duration,
                    'category' => $service->category,
                    'image' => $service->image,
                    'created_at' => $service->created_at,
                ];
            })
            ->toArray();
    }
}
