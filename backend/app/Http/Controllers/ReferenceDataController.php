<?php

namespace App\Http\Controllers;

use App\Models\ReferenceData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReferenceDataController extends Controller
{
    /**
     * Get all reference data
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReferenceData::query();

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->boolean('active', true)) {
            $query->active();
        }

        $data = $query->orderBy('sort_order')->orderBy('label')->get();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get reference data by category
     */
    public function getByCategory(string $category): JsonResponse
    {
        $data = ReferenceData::byCategory($category)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return response()->json([
            'category' => $category,
            'data' => $data,
        ]);
    }

    /**
     * Get specific categories
     */
    public function getCategories(Request $request): JsonResponse
    {
        $categories = $request->input('categories', []);

        if (empty($categories)) {
            return response()->json([
                'data' => [],
            ]);
        }

        $data = ReferenceData::whereIn('category', $categories)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->groupBy('category');

        return response()->json([
            'data' => $data,
        ]);
    }
}
