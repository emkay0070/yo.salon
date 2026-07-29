<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BrandExperience;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandExperienceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            $salonId = $request->attributes->get('salon_id');
            
            if (!$salonId) {
                return response()->json(['error' => 'Salon context not found'], 400);
            }
            
            $salon = Salon::find($salonId);
            
            if (!$salon) {
                return response()->json(['error' => 'Salon not found'], 404);
            }
            
            // Get or create brand experience
            $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
            
            // Load experience family config
            $experienceFamily = config('experience_families.' . $brand->experience_family, config('experience_families.luxury_noir'));
            
            if (!$experienceFamily) {
                return response()->json(['error' => 'Experience family config not found'], 500);
            }
            
            // Merge brand colors with experience family defaults
            $colors = array_merge(
                $experienceFamily['default_colors'] ?? [],
                [
                    'primary' => $brand->primary_color,
                    'secondary' => $brand->secondary_color,
                    'accent' => $brand->accent_color,
                ]
            );
            
            // Build complete brand response
            $brandResponse = [
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'slug' => $salon->slug,
                    'logo' => $brand->logo ?? $salon->logo,
                ],
                'brand' => [
                    'logo' => $brand->logo,
                    'primary_color' => $brand->primary_color,
                    'secondary_color' => $brand->secondary_color,
                    'accent_color' => $brand->accent_color,
                    'font_heading' => $brand->font_heading,
                    'font_body' => $brand->font_body,
                    'background_image' => $brand->background_image,
                    'custom_domain' => $brand->custom_domain,
                    'white_label_enabled' => $brand->white_label_enabled,
                ],
                'experience' => [
                    'family' => $brand->experience_family,
                    'name' => $experienceFamily['name'],
                    'description' => $experienceFamily['description'],
                    'glass_opacity' => $experienceFamily['glass_opacity'],
                    'glass_blur' => $experienceFamily['glass_blur'],
                    'shadow_style' => $experienceFamily['shadow_style'],
                    'shadow_intensity' => $experienceFamily['shadow_intensity'],
                    'border_radius' => $experienceFamily['border_radius'],
                    'border_radius_unit' => $experienceFamily['border_radius_unit'],
                    'card_style' => $experienceFamily['card_style'],
                    'motion_preset' => $experienceFamily['motion_preset'],
                    'animation_speed' => $experienceFamily['animation_speed'],
                    'spring_stiffness' => $experienceFamily['spring_stiffness'],
                    'spring_damping' => $experienceFamily['spring_damping'],
                    'icon_style' => $experienceFamily['icon_style'],
                    'icon_weight' => $experienceFamily['icon_weight'],
                    'background_type' => $experienceFamily['background_type'],
                    'cursor_style' => $experienceFamily['cursor_style'],
                    'sidebar_style' => $experienceFamily['sidebar_style'],
                    'button_style' => $experienceFamily['button_style'],
                ],
                'colors' => $colors,
            ];
            
            return response()->json($brandResponse);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }
        
        $salon = Salon::find($salonId);
        
        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }
        
        $validated = $request->validate([
            'experience_family' => 'sometimes|in:luxury_noir,modern_glass,urban_pulse,minimal_zen,organic,classic_barber,neon_future,executive',
            'logo' => 'sometimes|string|nullable',
            'primary_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_heading' => 'sometimes|in:sora,playfair,inter,poppins',
            'font_body' => 'sometimes|in:sora,playfair,inter,poppins',
            'background_image' => 'sometimes|string|nullable',
            'custom_domain' => 'sometimes|string|nullable',
            'white_label_enabled' => 'sometimes|boolean',
        ]);
        
        $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
        $brand->update($validated);
        
        return response()->json($brand);
    }
    
    private function createDefaultBrandExperience(Salon $salon): BrandExperience
    {
        return BrandExperience::create([
            'salon_id' => $salon->id,
            'experience_family' => 'luxury_noir',
            'logo' => $salon->logo,
            'primary_color' => '#FF622B',
            'secondary_color' => '#FF8C5A',
            'accent_color' => '#FFD700',
            'font_heading' => 'sora',
            'font_body' => 'inter',
            'white_label_enabled' => false,
        ]);
    }

    public function showBySlug(Request $request, string $slug): JsonResponse
    {
        try {
            $salon = Salon::where('slug', $slug)->first();
            
            if (!$salon) {
                return response()->json(['error' => 'Salon not found'], 404);
            }
            
            // Get or create brand experience
            $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
            
            // Load experience family config
            $experienceFamily = config('experience_families.' . $brand->experience_family, config('experience_families.luxury_noir'));
            
            if (!$experienceFamily) {
                return response()->json(['error' => 'Experience family config not found'], 500);
            }
            
            // Merge brand colors with experience family defaults
            $colors = array_merge(
                $experienceFamily['default_colors'] ?? [],
                [
                    'primary' => $brand->primary_color,
                    'secondary' => $brand->secondary_color,
                    'accent' => $brand->accent_color,
                ]
            );
            
            // Build complete brand response
            $brandResponse = [
                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'slug' => $salon->slug,
                    'logo' => $brand->logo ?? $salon->logo,
                ],
                'brand' => [
                    'logo' => $brand->logo,
                    'primary_color' => $brand->primary_color,
                    'secondary_color' => $brand->secondary_color,
                    'accent_color' => $brand->accent_color,
                    'font_heading' => $brand->font_heading,
                    'font_body' => $brand->font_body,
                    'background_image' => $brand->background_image,
                    'custom_domain' => $brand->custom_domain,
                    'white_label_enabled' => $brand->white_label_enabled,
                ],
                'experience' => [
                    'family' => $brand->experience_family,
                    'name' => $experienceFamily['name'],
                    'description' => $experienceFamily['description'],
                    'glass_opacity' => $experienceFamily['glass_opacity'],
                    'glass_blur' => $experienceFamily['glass_blur'],
                    'shadow_style' => $experienceFamily['shadow_style'],
                    'shadow_intensity' => $experienceFamily['shadow_intensity'],
                    'border_radius' => $experienceFamily['border_radius'],
                    'border_radius_unit' => $experienceFamily['border_radius_unit'],
                    'card_style' => $experienceFamily['card_style'],
                    'motion_preset' => $experienceFamily['motion_preset'],
                    'animation_speed' => $experienceFamily['animation_speed'],
                    'spring_stiffness' => $experienceFamily['spring_stiffness'],
                    'spring_damping' => $experienceFamily['spring_damping'],
                    'icon_style' => $experienceFamily['icon_style'],
                    'icon_weight' => $experienceFamily['icon_weight'],
                    'background_type' => $experienceFamily['background_type'],
                    'cursor_style' => $experienceFamily['cursor_style'],
                    'sidebar_style' => $experienceFamily['sidebar_style'],
                    'button_style' => $experienceFamily['button_style'],
                ],
                'colors' => $colors,
            ];
            
            return response()->json($brandResponse);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
