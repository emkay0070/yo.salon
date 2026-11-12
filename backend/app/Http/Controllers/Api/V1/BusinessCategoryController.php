<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessCategoryController extends Controller
{
    /**
     * Get all active business categories, optionally filtered by provider_type
     */
    public function index(Request $request)
    {
        $query = BusinessCategory::active();

        if ($request->has('provider_type')) {
            $query->byProviderType($request->provider_type);
        }

        $categories = $query->ordered()
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'provider_type' => $category->provider_type,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'short_name' => $category->short_name,
                    'description' => $category->description,
                    'icon' => $category->icon,
                    'default_specialist_label' => $category->default_specialist_label,
                    'default_specialist_plural' => $category->default_specialist_plural,
                    'default_appointment_label' => $category->default_appointment_label,
                    'default_customer_term' => $category->default_customer_term,
                ];
            });

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get a specific business category by slug
     */
    public function show($slug)
    {
        $category = BusinessCategory::bySlug($slug)->active()->first();

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'short_name' => $category->short_name,
                'description' => $category->description,
                'icon' => $category->icon,
                'default_specialist_label' => $category->default_specialist_label,
                'default_specialist_plural' => $category->default_specialist_plural,
                'default_appointment_label' => $category->default_appointment_label,
                'default_customer_term' => $category->default_customer_term,
            ],
        ]);
    }
}
