<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BrandExperience;
use App\Models\Salon;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandExperienceController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $salonId = $request->attributes->get('salon_id');

            if (!$salonId) {
                \Log::warning('Brand experience: No salon context', [
                    'request' => $request->all(),
                ]);
                return response()->json(['error' => 'Salon context not found'], 400);
            }

            $salon = Salon::find($salonId);

            if (!$salon) {
                \Log::warning('Brand experience: Salon not found', [
                    'salon_id' => $salonId,
                ]);
                return response()->json(['error' => 'Salon not found'], 404);
            }

            // Get or create brand experience
            try {
                $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
            } catch (\Exception $e) {
                \Log::error('Brand experience: Failed to get/create brand', [
                    'error' => $e->getMessage(),
                    'salon_id' => $salonId,
                ]);
                // Return default brand experience
                $brand = $this->createDefaultBrandExperience($salon);
            }

            // Load experience family config
            $experienceFamily = config('experience_families.' . $brand->experience_family, config('experience_families.luxury_noir'));

            if (!$experienceFamily) {
                \Log::warning('Brand experience: Experience family not found', [
                    'experience_family' => $brand->experience_family,
                ]);
                $experienceFamily = config('experience_families.luxury_noir');
            }

            // Merge brand colors with experience family defaults
            $colors = array_merge(
                $experienceFamily['default_colors'] ?? [],
                [
                    'primary' => $brand->primary_color ?? '#FF622B',
                    'secondary' => $brand->secondary_color ?? '#FF8C5A',
                    'accent' => $brand->accent_color ?? '#FFD700',
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
                    'primary_color' => $brand->primary_color ?? '#FF622B',
                    'secondary_color' => $brand->secondary_color ?? '#FF8C5A',
                    'accent_color' => $brand->accent_color ?? '#FFD700',
                    'font_heading' => $brand->font_heading ?? 'sora',
                    'font_body' => $brand->font_body ?? 'inter',
                    'background_image' => $brand->background_image,
                    'custom_domain' => $brand->custom_domain,
                    'white_label_enabled' => $brand->white_label_enabled ?? false,
                ],
                'experience' => [
                    'family' => $brand->experience_family ?? 'luxury_noir',
                    'name' => $experienceFamily['name'] ?? 'Luxury Noir',
                    'description' => $experienceFamily['description'] ?? 'Sophisticated, premium dark theme with gold accents',
                    'glass_opacity' => $experienceFamily['glass_opacity'] ?? 0.08,
                    'glass_blur' => $experienceFamily['glass_blur'] ?? 40,
                    'shadow_style' => $experienceFamily['shadow_style'] ?? 'deep',
                    'shadow_intensity' => $experienceFamily['shadow_intensity'] ?? 1.0,
                    'border_radius' => $experienceFamily['border_radius'] ?? 16,
                    'border_radius_unit' => $experienceFamily['border_radius_unit'] ?? 'px',
                    'card_style' => $experienceFamily['card_style'] ?? 'floating',
                    'motion_preset' => $experienceFamily['motion_preset'] ?? 'cinematic',
                    'animation_speed' => $experienceFamily['animation_speed'] ?? 1.0,
                    'spring_stiffness' => $experienceFamily['spring_stiffness'] ?? 300,
                    'spring_damping' => $experienceFamily['spring_damping'] ?? 25,
                    'icon_style' => $experienceFamily['icon_style'] ?? 'luxury',
                    'icon_weight' => $experienceFamily['icon_weight'] ?? 'regular',
                    'background_type' => $experienceFamily['background_type'] ?? 'salon_image',
                    'cursor_style' => $experienceFamily['cursor_style'] ?? 'luxury',
                    'sidebar_style' => $experienceFamily['sidebar_style'] ?? 'glass',
                    'button_style' => $experienceFamily['button_style'] ?? 'glass',
                ],
                'colors' => $colors,
            ];

            return response()->json($brandResponse);
        } catch (\Exception $e) {
            \Log::error('Brand experience error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salon_id' => $request->attributes->get('salon_id'),
            ]);

            // Return safe default brand experience
            return response()->json([
                'salon' => [
                    'id' => $request->attributes->get('salon_id'),
                    'name' => 'Salon',
                    'slug' => 'salon',
                    'logo' => null,
                ],
                'brand' => [
                    'logo' => null,
                    'primary_color' => '#FF622B',
                    'secondary_color' => '#FF8C5A',
                    'accent_color' => '#FFD700',
                    'font_heading' => 'sora',
                    'font_body' => 'inter',
                    'background_image' => null,
                    'custom_domain' => null,
                    'white_label_enabled' => false,
                ],
                'experience' => [
                    'family' => 'luxury_noir',
                    'name' => 'Luxury Noir',
                    'description' => 'Sophisticated, premium dark theme with gold accents',
                    'glass_opacity' => 0.08,
                    'glass_blur' => 40,
                    'shadow_style' => 'deep',
                    'shadow_intensity' => 1.0,
                    'border_radius' => 16,
                    'border_radius_unit' => 'px',
                    'card_style' => 'floating',
                    'motion_preset' => 'cinematic',
                    'animation_speed' => 1.0,
                    'spring_stiffness' => 300,
                    'spring_damping' => 25,
                    'icon_style' => 'luxury',
                    'icon_weight' => 'regular',
                    'background_type' => 'salon_image',
                    'cursor_style' => 'luxury',
                    'sidebar_style' => 'glass',
                    'button_style' => 'glass',
                ],
                'colors' => [
                    'primary' => '#FF622B',
                    'secondary' => '#FF8C5A',
                    'accent' => '#FFD700',
                    'background' => '#0A0A0A',
                    'surface' => '#1A1A1A',
                ],
            ]);
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
            'logo' => 'sometimes|image|max:2048',
            'primary_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_heading' => 'sometimes|in:sora,playfair,inter,poppins',
            'font_body' => 'sometimes|in:sora,playfair,inter,poppins',
            'background_image' => 'sometimes|image|max:5120',
            'custom_domain' => 'sometimes|string|nullable',
            'white_label_enabled' => 'sometimes|boolean',
        ]);
        
        $data = $validated;
        
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
            
            // Delete old media if exists
            if ($brand->logo && \Illuminate\Support\Str::isUuid($brand->logo)) {
                $oldMedia = \App\Models\Media::find($brand->logo);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            }
            
            $media = $this->mediaService->upload($request->file('logo'), [
                'directory' => 'brand/logos',
                'provider_id' => $salon->provider_id,
                'salon_id' => $salon->id,
                'alt_text' => $salon->name . ' logo',
            ]);
            $data['logo'] = $media->id;
        }
        
        // Handle background image upload
        if ($request->hasFile('background_image')) {
            $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
            
            // Delete old media if exists
            if ($brand->background_image && \Illuminate\Support\Str::isUuid($brand->background_image)) {
                $oldMedia = \App\Models\Media::find($brand->background_image);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            }
            
            $media = $this->mediaService->upload($request->file('background_image'), [
                'directory' => 'brand/backgrounds',
                'provider_id' => $salon->provider_id,
                'salon_id' => $salon->id,
                'alt_text' => $salon->name . ' background',
            ]);
            $data['background_image'] = $media->id;
        }
        
        $brand = $salon->brandExperience ?? $this->createDefaultBrandExperience($salon);
        $brand->update($data);
        
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
