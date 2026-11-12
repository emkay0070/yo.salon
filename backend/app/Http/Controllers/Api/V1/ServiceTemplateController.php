<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceTemplate;
use App\Models\BusinessCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceTemplateController extends Controller
{
    /**
     * Get service templates for a specific business category
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'category_slug' => 'required|string|exists:business_categories,slug',
        ]);

        $category = BusinessCategory::bySlug($request->category_slug)->first();

        if (!$category) {
            return response()->json([
                'message' => 'Business category not found',
            ], 404);
        }

        $templates = $category->serviceTemplates()
            ->with('serviceTemplate')
            ->recommended()
            ->ordered()
            ->get()
            ->map(function ($categoryTemplate) {
                $template = $categoryTemplate->serviceTemplate;
                return [
                    'id' => $template->id,
                    'slug' => $template->slug,
                    'name' => $template->name,
                    'description' => $template->description,
                    'icon' => $template->icon,
                    'is_recommended' => $categoryTemplate->is_recommended,
                ];
            });

        return response()->json([
            'data' => $templates,
            'category' => [
                'slug' => $category->slug,
                'name' => $category->name,
                'default_specialist_label' => $category->default_specialist_label,
                'default_specialist_plural' => $category->default_specialist_plural,
            ],
        ]);
    }

    /**
     * Get all service templates (admin use)
     */
    public function all(Request $request): JsonResponse
    {
        $templates = ServiceTemplate::active()
            ->ordered()
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'slug' => $template->slug,
                    'name' => $template->name,
                    'description' => $template->description,
                    'icon' => $template->icon,
                ];
            });

        return response()->json([
            'data' => $templates,
        ]);
    }
}
