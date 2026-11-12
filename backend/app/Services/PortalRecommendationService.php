<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Service;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class PortalRecommendationService
{
    /**
     * Get recommended services for a customer at a specific provider
     * Based on visit history, preferred category, seasonal campaigns
     */
    public function getRecommendedServices(Customer $customer, string $providerId, int $limit = 6): array
    {
        try {
            $customerId = $customer->id;

            if (!$providerId) {
                return [];
            }

            // Get customer's booking history to find frequently booked services at this provider
            $frequentlyBookedServiceIds = Booking::where('customer_id', $customerId)
                ->where('provider_id', $providerId)
                ->where('status', 'completed')
                ->select('service_id', DB::raw('COUNT(*) as count'))
                ->groupBy('service_id')
                ->orderBy('count', 'desc')
                ->limit(3)
                ->pluck('service_id')
                ->toArray();

            // Get services from the same category as frequently booked services
            $categoryIds = [];
            if (!empty($frequentlyBookedServiceIds)) {
                $categoryIds = Service::whereIn('id', $frequentlyBookedServiceIds)
                    ->where('provider_id', $providerId)
                    ->pluck('category')
                    ->unique()
                    ->toArray();
            }

            // Build query for recommended services
            $query = Service::where('provider_id', $providerId)
                ->where('active', true);

            // Prioritize services from same category as frequently booked
            if (!empty($categoryIds)) {
                $categoryPriority = implode(',', array_map(function($id) { return "'$id'"; }, $categoryIds));
                try {
                    $query->orderByRaw("CASE WHEN category IN ($categoryPriority) THEN 0 ELSE 1 END");
                } catch (\Exception $e) {
                    \Log::warning('Failed to order by category priority', ['error' => $e->getMessage()]);
                }
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
                        'image_url' => $service->image_url,
                    ];
                })
                ->toArray();

            return $services;
        } catch (\Exception $e) {
            \Log::error('getRecommendedServices error', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'provider_id' => $providerId,
            ]);
            return [];
        }
    }

    /**
     * Get trending services
     * Based on booking count in the last 30 days
     */
    public function getTrendingServices(string $providerId, int $limit = 6): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        if (!$providerId) {
            return [];
        }

        $trendingServiceIds = Booking::where('provider_id', $providerId)
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
            return Service::where('provider_id', $providerId)
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
            ->where('provider_id', $providerId)
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
    public function getPopularServices(string $providerId, int $limit = 6): array
    {
        if (!$providerId) {
            return [];
        }

        $popularServiceIds = Booking::where('provider_id', $providerId)
            ->where('status', '!=', 'cancelled')
            ->select('service_id', DB::raw('COUNT(*) as count'))
            ->groupBy('service_id')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->pluck('service_id')
            ->toArray();

        if (empty($popularServiceIds)) {
            // Fallback to active services
            return Service::where('provider_id', $providerId)
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
            ->where('provider_id', $providerId)
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
    public function getSeasonalServices(string $providerId, int $limit = 6): array
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

        return Service::where('provider_id', $providerId)
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
    public function getRecentlyAddedServices(string $providerId, int $limit = 6): array
    {
        return Service::where('provider_id', $providerId)
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
