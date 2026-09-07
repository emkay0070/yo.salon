<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Specialist;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * Universal search across all entities
     */
    public function universal(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 10);
        $type = $request->get('type', 'all'); // all, providers, specialists, services

        if (strlen($query) < 2) {
            return response()->json([
                'providers' => [],
                'specialists' => [],
                'services' => [],
                'total' => 0,
            ]);
        }

        $results = [
            'providers' => [],
            'specialists' => [],
            'services' => [],
            'total' => 0,
        ];

        // Search providers
        if ($type === 'all' || $type === 'providers') {
            $providers = Provider::query()
                ->where('display_name', 'ILIKE', "%{$query}%")
                ->orWhere('description', 'ILIKE', "%{$query}%")
                ->orWhere('location', 'ILIKE', "%{$query}%")
                ->where('status', 'active')
                ->with(['salons'])
                ->limit($limit)
                ->get();

            $results['providers'] = $providers->map(function ($provider) {
                return [
                    'id' => $provider->id,
                    'type' => 'provider',
                    'display_name' => $provider->display_name,
                    'slug' => $provider->slug,
                    'description' => $provider->description,
                    'type_label' => ucfirst($provider->type),
                    'location' => $provider->location,
                    'rating' => $provider->rating ?? 0,
                    'review_count' => $provider->review_count ?? 0,
                    'logo' => $provider->logo,
                    'cover_image' => $provider->cover_image,
                ];
            })->toArray();
        }

        // Search specialists
        if ($type === 'all' || $type === 'specialists') {
            $specialists = Specialist::query()
                ->where('name', 'ILIKE', "%{$query}%")
                ->orWhere('role', 'ILIKE', "%{$query}%")
                ->orWhere('bio', 'ILIKE', "%{$query}%")
                ->orWhereJsonContains('skills', $query)
                ->where('is_active', true)
                ->with(['provider'])
                ->limit($limit)
                ->get();

            $results['specialists'] = $specialists->map(function ($specialist) {
                return [
                    'id' => $specialist->id,
                    'type' => 'specialist',
                    'name' => $specialist->name,
                    'handle' => $specialist->handle,
                    'role' => $specialist->role,
                    'bio' => $specialist->bio,
                    'skills' => $specialist->skills,
                    'years_experience' => $specialist->years_experience,
                    'rating' => $specialist->rating ?? 0,
                    'review_count' => $specialist->review_count ?? 0,
                    'photo' => $specialist->photo,
                    'profile_image' => $specialist->profile_image,
                    'provider' => $specialist->provider ? [
                        'id' => $specialist->provider->id,
                        'display_name' => $specialist->provider->display_name,
                        'slug' => $specialist->provider->slug,
                    ] : null,
                ];
            })->toArray();
        }

        // Search services
        if ($type === 'all' || $type === 'services') {
            $services = Service::query()
                ->where('name', 'ILIKE', "%{$query}%")
                ->orWhere('description', 'ILIKE', "%{$query}%")
                ->orWhere('category', 'ILIKE', "%{$query}%")
                ->where('is_active', true)
                ->with(['salon'])
                ->limit($limit)
                ->get();

            $results['services'] = $services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'type' => 'service',
                    'name' => $service->name,
                    'description' => $service->description,
                    'category' => $service->category,
                    'duration' => $service->duration,
                    'price' => $service->price,
                    'currency' => $service->currency ?? 'UGX',
                    'salon' => $service->salon ? [
                        'id' => $service->salon->id,
                        'name' => $service->salon->name,
                        'slug' => $service->salon->slug,
                    ] : null,
                ];
            })->toArray();
        }

        $results['total'] = count($results['providers']) + count($results['specialists']) + count($results['services']);

        return response()->json($results);
    }

    /**
     * Search suggestions (autocomplete)
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = $request->get('limit', 5);

        if (strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $suggestions = [];

        // Get top matching providers
        $providers = Provider::query()
            ->where('display_name', 'ILIKE', "{$query}%")
            ->where('status', 'active')
            ->limit(2)
            ->get();

        foreach ($providers as $provider) {
            $suggestions[] = [
                'text' => $provider->display_name,
                'type' => 'provider',
                'id' => $provider->id,
                'icon' => 'store',
            ];
        }

        // Get top matching specialists
        $specialists = Specialist::query()
            ->where('name', 'ILIKE', "{$query}%")
            ->where('is_active', true)
            ->limit(2)
            ->get();

        foreach ($specialists as $specialist) {
            $suggestions[] = [
                'text' => $specialist->name,
                'type' => 'specialist',
                'id' => $specialist->id,
                'icon' => 'user',
            ];
        }

        // Get top matching services
        $services = Service::query()
            ->where('name', 'ILIKE', "{$query}%")
            ->where('is_active', true)
            ->limit(2)
            ->get();

        foreach ($services as $service) {
            $suggestions[] = [
                'text' => $service->name,
                'type' => 'service',
                'id' => $service->id,
                'icon' => 'scissors',
            ];
        }

        return response()->json(['suggestions' => $suggestions]);
    }
}
