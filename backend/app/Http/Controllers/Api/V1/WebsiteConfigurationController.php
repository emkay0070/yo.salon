<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebsiteConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteConfigurationController extends Controller
{
    /**
     * Get website configuration for a salon or provider
     */
    public function show(Request $request, string $type, string $id): JsonResponse
    {
        $config = WebsiteConfiguration::where('configurable_type', $type)
            ->where('configurable_id', $id)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            // Return default configuration
            return response()->json([
                'enabled_sections' => ['hero', 'services', 'team', 'contact'],
                'section_order' => ['hero', 'services', 'team', 'contact'],
                'theme' => 'luxury_noir',
                'custom_colors' => null,
                'custom_fonts' => null,
                'theme_config' => WebsiteConfiguration::getAvailableThemes()['luxury_noir'] ?? [],
            ]);
        }

        return response()->json([
            'enabled_sections' => $config->enabled_sections,
            'section_order' => $config->section_order,
            'theme' => $config->theme,
            'custom_colors' => $config->custom_colors,
            'custom_fonts' => $config->custom_fonts,
            'theme_config' => $config->getThemeConfig(),
            'final_colors' => $config->getFinalColors(),
        ]);
    }

    /**
     * Update website configuration
     */
    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $request->validate([
            'enabled_sections' => 'array',
            'enabled_sections.*' => 'string|in:hero,services,team,gallery,testimonials,products,faqs,contact,book_now,careers',
            'section_order' => 'array',
            'section_order.*' => 'string|in:hero,services,team,gallery,testimonials,products,faqs,contact,book_now,careers',
            'theme' => 'string|in:luxury_noir,minimal_studio,modern_glass,urban_edge,natural_spa,classic_beauty',
            'custom_colors' => 'nullable|array',
            'custom_fonts' => 'nullable|array',
        ]);

        $config = WebsiteConfiguration::updateOrCreate(
            [
                'configurable_type' => $type,
                'configurable_id' => $id,
            ],
            [
                'enabled_sections' => $request->input('enabled_sections', ['hero', 'services', 'team', 'contact']),
                'section_order' => $request->input('section_order', ['hero', 'services', 'team', 'contact']),
                'theme' => $request->input('theme', 'luxury_noir'),
                'custom_colors' => $request->input('custom_colors'),
                'custom_fonts' => $request->input('custom_fonts'),
                'is_active' => true,
            ]
        );

        return response()->json([
            'enabled_sections' => $config->enabled_sections,
            'section_order' => $config->section_order,
            'theme' => $config->theme,
            'custom_colors' => $config->custom_colors,
            'custom_fonts' => $config->custom_fonts,
            'theme_config' => $config->getThemeConfig(),
            'final_colors' => $config->getFinalColors(),
        ]);
    }

    /**
     * Reset to default configuration
     */
    public function reset(Request $request, string $type, string $id): JsonResponse
    {
        WebsiteConfiguration::where('configurable_type', $type)
            ->where('configurable_id', $id)
            ->delete();

        return response()->json([
            'message' => 'Configuration reset to defaults',
            'default_config' => [
                'enabled_sections' => ['hero', 'services', 'team', 'contact'],
                'section_order' => ['hero', 'services', 'team', 'contact'],
                'theme' => 'luxury_noir',
            ],
        ]);
    }
}
