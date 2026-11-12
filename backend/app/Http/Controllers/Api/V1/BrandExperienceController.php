<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BrandExperience;
use App\Models\Provider;
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
            $providerId = $request->attributes->get('provider_id');

            if (!$providerId) {
                \Log::warning('Brand experience: No provider context', [
                    'request' => $request->all(),
                ]);
                return response()->json(['error' => 'Provider context not found'], 400);
            }

            $provider = Provider::with('logoMedia')->find($providerId);

            if (!$provider) {
                \Log::warning('Brand experience: Provider not found', [
                    'provider_id' => $providerId,
                ]);
                return response()->json(['error' => 'Provider not found'], 404);
            }

            // Get or create brand experience
            try {
                $brand = $provider->brandExperience ?? $this->createDefaultBrandExperience($provider);
            } catch (\Exception $e) {
                \Log::error('Brand experience: Failed to get/create brand', [
                    'error' => $e->getMessage(),
                    'provider_id' => $providerId,
                ]);
                // Return default brand experience
                $brand = $this->createDefaultBrandExperience($provider);
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
                'provider' => [
                    'id' => $provider->id,
                    'name' => $provider->display_name,
                    'slug' => $provider->slug,
                    'logo' => $provider->logo,
                    'logo_url' => $provider->logo_url,
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
                'provider_id' => $request->attributes->get('provider_id'),
            ]);

            // Return safe default brand experience
            return response()->json([
                'provider' => [
                    'id' => $request->attributes->get('provider_id'),
                    'name' => 'Provider',
                    'slug' => 'provider',
                    'logo' => null,
                    'logo_url' => null,
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
        $providerId = $request->attributes->get('provider_id');
        
        if (!$providerId) {
            return response()->json(['error' => 'Provider context not found'], 400);
        }
        
        $provider = Provider::find($providerId);
        
        if (!$provider) {
            return response()->json(['error' => 'Provider not found'], 404);
        }
        
        $validated = $request->validate([
            'experience_family' => 'sometimes|in:luxury_noir,modern_glass,urban_pulse,minimal_zen,organic,classic_barber,neon_future,executive',
            // 'logo' - DEPRECATED: Brand Experience does not own identity assets. Use Provider.logo instead.
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
        $data['provider_id'] = $provider->id;
        
        // Logo handling: Brand Experience does not own identity assets
        // Logo is managed via ProviderController::uploadLogo()
        // BrandExperience.logo_url delegates to Provider.logo_url
        
        // Handle background image upload
        if ($request->hasFile('background_image')) {
            $brand = $provider->brandExperience ?? $this->createDefaultBrandExperience($provider);
            
            // Delete old media if exists
            if ($brand->background_image && \Illuminate\Support\Str::isUuid($brand->background_image)) {
                $oldMedia = \App\Models\Media::find($brand->background_image);
                if ($oldMedia) {
                    $this->mediaService->delete($oldMedia);
                }
            }
            
            $media = $this->mediaService->upload($request->file('background_image'), [
                'directory' => 'brand/backgrounds',
                'provider_id' => $provider->id,
                'attachable' => $brand,
                'visibility' => \App\Models\Media::VISIBILITY_PUBLIC,
                'alt_text' => $provider->display_name . ' background',
            ]);
            $data['background_image'] = $media->id;
        }
        
        $brand = $provider->brandExperience ?? $this->createDefaultBrandExperience($provider);
        $brand->update($data);
        
        return response()->json($brand);
    }
    
    private function createDefaultBrandExperience(Provider $provider): BrandExperience
    {
        return BrandExperience::create([
            'provider_id' => $provider->id,
            'experience_family' => 'luxury_noir',
            'logo' => $provider->logo,
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
            $provider = Provider::with('logoMedia')->where('slug', $slug)->first();
            
            if (!$provider) {
                return response()->json(['error' => 'Provider not found'], 404);
            }
            
            // Get or create brand experience
            $brand = $provider->brandExperience ?? $this->createDefaultBrandExperience($provider);
            
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
                'provider' => [
                    'id' => $provider->id,
                    'name' => $provider->display_name ?? $provider->name,
                    'slug' => $provider->slug,
                    'logo' => $brand->logo ?? $provider->logo,
                    'logo_url' => $provider->logo_url,
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
