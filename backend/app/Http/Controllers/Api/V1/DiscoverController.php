<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DiscoverController extends Controller
{
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 6);
        $type = $request->query('type'); // 'salon', 'service', 'specialist', or null for all

        $data = [];

        if (!$type || $type === 'salon') {
            $data['salons'] = Salon::where('is_featured', true)
                ->with('provider')
                ->limit($limit)
                ->get()
                ->map(function ($salon) {
                    return [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'slug' => $salon->slug,
                        'logo' => $salon->logo,
                        'cover_image' => $salon->cover_image,
                        'address' => $salon->address,
                        'city' => $salon->city,
                        'rating' => $salon->rating ?? 0,
                        'review_count' => $salon->review_count ?? 0,
                        'provider' => $salon->provider ? [
                            'id' => $salon->provider->id,
                            'name' => $salon->provider->display_name,
                        ] : null,
                    ];
                });
        }

        if (!$type || $type === 'service') {
            $data['services'] = Service::where('is_featured', true)
                ->where('active', true)
                ->with('provider')
                ->limit($limit)
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'category' => $service->category,
                        'price' => $service->price,
                        'duration' => $service->duration,
                        'image_url' => $service->image_url,
                        'rating' => $service->rating ?? 0,
                        'popularity' => $service->bookings_count ?? 0,
                        'provider' => $service->provider ? [
                            'id' => $service->provider->id,
                            'name' => $service->provider->display_name,
                        ] : null,
                    ];
                });
        }

        if (!$type || $type === 'specialist') {
            $data['specialists'] = Specialist::where('is_featured', true)
                ->where('active', true)
                ->with(['provider', 'salons'])
                ->limit($limit)
                ->get()
                ->map(function ($specialist) {
                    return [
                        'id' => $specialist->id,
                        'name' => $specialist->name,
                        'handle' => $specialist->handle,
                        'role' => $specialist->role,
                        'photo' => $specialist->photo_url,
                        'specialties' => $specialist->specialties,
                        'rating' => $specialist->rating ?? 0,
                        'review_count' => $specialist->review_count ?? 0,
                        'follower_count' => $specialist->follower_count ?? 0,
                        'provider' => $specialist->provider ? [
                            'id' => $specialist->provider->id,
                            'name' => $specialist->provider->display_name,
                        ] : null,
                    ];
                });
        }

        return response()->json($data);
    }

    public function trending(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 6);
        $type = $request->query('type'); // 'salon', 'service', 'specialist', or null for all

        $data = [];

        if (!$type || $type === 'salon') {
            $data['salons'] = Salon::orderBy('trending_score', 'desc')
                ->with('provider')
                ->limit($limit)
                ->get()
                ->map(function ($salon) {
                    return [
                        'id' => $salon->id,
                        'name' => $salon->name,
                        'slug' => $salon->slug,
                        'logo' => $salon->logo,
                        'cover_image' => $salon->cover_image,
                        'address' => $salon->address,
                        'city' => $salon->city,
                        'rating' => $salon->rating ?? 0,
                        'review_count' => $salon->review_count ?? 0,
                        'trending_score' => $salon->trending_score,
                        'view_count' => $salon->view_count,
                        'provider' => $salon->provider ? [
                            'id' => $salon->provider->id,
                            'name' => $salon->provider->display_name,
                        ] : null,
                    ];
                });
        }

        if (!$type || $type === 'service') {
            $data['services'] = Service::where('active', true)
                ->orderBy('trending_score', 'desc')
                ->with('provider')
                ->limit($limit)
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'description' => $service->description,
                        'category' => $service->category,
                        'price' => $service->price,
                        'duration' => $service->duration,
                        'image_url' => $service->image_url,
                        'rating' => $service->rating ?? 0,
                        'popularity' => $service->bookings_count ?? 0,
                        'trending_score' => $service->trending_score,
                        'view_count' => $service->view_count,
                        'provider' => $service->provider ? [
                            'id' => $service->provider->id,
                            'name' => $service->provider->display_name,
                        ] : null,
                    ];
                });
        }

        if (!$type || $type === 'specialist') {
            $data['specialists'] = Specialist::where('active', true)
                ->orderBy('trending_score', 'desc')
                ->with(['provider', 'salons'])
                ->limit($limit)
                ->get()
                ->map(function ($specialist) {
                    return [
                        'id' => $specialist->id,
                        'name' => $specialist->name,
                        'handle' => $specialist->handle,
                        'role' => $specialist->role,
                        'photo' => $specialist->photo_url,
                        'specialties' => $specialist->specialties,
                        'rating' => $specialist->rating ?? 0,
                        'review_count' => $specialist->review_count ?? 0,
                        'follower_count' => $specialist->follower_count ?? 0,
                        'trending_score' => $specialist->trending_score,
                        'view_count' => $specialist->view_count,
                        'provider' => $specialist->provider ? [
                            'id' => $specialist->provider->id,
                            'name' => $specialist->provider->display_name,
                        ] : null,
                    ];
                });
        }

        return response()->json($data);
    }

    public function setFeatured(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:salon,service,specialist',
            'id' => 'required|uuid',
            'featured' => 'required|boolean',
        ]);

        $type = $request->input('type');
        $id = $request->input('id');
        $featured = $request->input('featured');

        $model = match($type) {
            'salon' => Salon::find($id),
            'service' => Service::find($id),
            'specialist' => Specialist::find($id),
        };

        if (!$model) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $model->is_featured = $featured;
        $model->save();

        return response()->json(['message' => 'Featured status updated', 'is_featured' => $model->is_featured]);
    }

    public function refreshTrendingScores(Request $request): JsonResponse
    {
        $service = app(\App\Services\TrendingScoreService::class);

        $type = $request->query('type'); // 'salon', 'service', 'specialist', or null for all

        if (!$type || $type === 'salon') {
            $service->refreshAllSalonScores();
        }

        if (!$type || $type === 'service') {
            $service->refreshAllServiceScores();
        }

        if (!$type || $type === 'specialist') {
            $service->refreshAllSpecialistScores();
        }

        return response()->json(['message' => 'Trending scores refreshed']);
    }
}
