<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\WebsiteConfiguration;
use App\Models\Provider;

class WebsiteConfigurationResolver
{
    /**
     * Resolve the effective website configuration for a salon
     */
    public function resolve(Salon $salon): array
    {
        $config = $this->getConfiguration($salon);
        
        return [
            'salon' => $this->resolveSalonInfo($salon),
            'brand' => $this->resolveBranding($salon, $config),
            'theme' => $this->resolveTheme($config),
            'sections' => $this->resolveSections($config),
            'navigation' => $this->resolveNavigation($config),
        ];
    }

    /**
     * Get the website configuration (custom or default)
     */
    protected function getConfiguration(Salon $salon): ?WebsiteConfiguration
    {
        // Try to get custom configuration
        $config = WebsiteConfiguration::where('configurable_type', Salon::class)
            ->where('configurable_id', $salon->id)
            ->where('is_active', true)
            ->first();

        // If no custom config, check provider-level config
        if (!$config && $salon->provider) {
            $config = WebsiteConfiguration::where('configurable_type', Provider::class)
                ->where('configurable_id', $salon->provider->id)
                ->where('is_active', true)
                ->first();
        }

        return $config;
    }

    /**
     * Resolve salon information
     */
    protected function resolveSalonInfo(Salon $salon): array
    {
        return [
            'id' => $salon->id,
            'name' => $salon->name,
            'slug' => $salon->slug,
            'description' => $salon->description,
            'logo' => $salon->logo_url ?? null,
            'phone' => $salon->phone,
            'email' => $salon->email,
            'address' => $salon->address,
            'city' => $salon->city,
            'whatsapp' => $salon->whatsapp,
            'lat' => $salon->lat,
            'lng' => $salon->lng,
            'opening_hours' => $salon->opening_hours ?? [],
        ];
    }

    /**
     * Resolve branding information
     */
    protected function resolveBranding(Salon $salon, ?WebsiteConfiguration $config): array
    {
        $brandExperience = $salon->brandExperience;

        // Use custom colors from config if available, otherwise fall back to brand experience
        $customColors = $config ? json_decode($config->custom_colors, true) : null;
        $customFonts = $config ? json_decode($config->custom_fonts, true) : null;

        return [
            'logo' => $config?->logo ?? $brandExperience?->logo ?? $salon->logo_url,
            'primary_color' => $customColors['primary'] ?? $brandExperience?->primary_color ?? '#FF622B',
            'secondary_color' => $customColors['secondary'] ?? $brandExperience?->secondary_color ?? '#FF8C5A',
            'accent_color' => $customColors['accent'] ?? $brandExperience?->accent_color ?? '#FFD700',
            'font_heading' => $customFonts['font_heading'] ?? $brandExperience?->font_heading ?? 'sora',
            'font_body' => $customFonts['font_body'] ?? $brandExperience?->font_body ?? 'inter',
            'background_image' => $brandExperience?->background_image,
            'custom_domain' => $brandExperience?->custom_domain,
            'white_label_enabled' => $brandExperience?->white_label_enabled ?? false,
        ];
    }

    /**
     * Resolve theme configuration
     */
    protected function resolveTheme(?WebsiteConfiguration $config): array
    {
        $brandExperience = $config?->configurable?->brandExperience;
        $experienceFamily = $brandExperience?->experience_family ?? 'luxury_noir';
        
        $themeConfig = config('experience_families.' . $experienceFamily, config('experience_families.luxury_noir'));

        return [
            'family' => $experienceFamily,
            'name' => $themeConfig['name'] ?? 'Luxury Noir',
            'description' => $themeConfig['description'] ?? 'Sophisticated, premium dark theme with gold accents',
            'glass_opacity' => $themeConfig['glass_opacity'] ?? 0.08,
            'glass_blur' => $themeConfig['glass_blur'] ?? 40,
            'shadow_style' => $themeConfig['shadow_style'] ?? 'deep',
            'shadow_intensity' => $themeConfig['shadow_intensity'] ?? 1.0,
            'border_radius' => $themeConfig['border_radius'] ?? 16,
            'border_radius_unit' => $themeConfig['border_radius_unit'] ?? 'px',
            'card_style' => $themeConfig['card_style'] ?? 'floating',
            'motion_preset' => $themeConfig['motion_preset'] ?? 'cinematic',
            'animation_speed' => $themeConfig['animation_speed'] ?? 1.0,
            'spring_stiffness' => $themeConfig['spring_stiffness'] ?? 300,
            'spring_damping' => $themeConfig['spring_damping'] ?? 25,
            'icon_style' => $themeConfig['icon_style'] ?? 'luxury',
            'icon_weight' => $themeConfig['icon_weight'] ?? 'regular',
            'background_type' => $themeConfig['background_type'] ?? 'salon_image',
            'cursor_style' => $themeConfig['cursor_style'] ?? 'luxury',
            'sidebar_style' => $themeConfig['sidebar_style'] ?? 'glass',
            'button_style' => $themeConfig['button_style'] ?? 'glass',
        ];
    }

    /**
     * Resolve enabled sections and their order
     */
    protected function resolveSections(?WebsiteConfiguration $config): array
    {
        $enabledSections = $config ? json_decode($config->enabled_sections, true) : ['hero', 'services', 'team', 'contact'];
        $sectionOrder = $config ? json_decode($config->section_order, true) : ['hero', 'about', 'services', 'team', 'gallery', 'testimonials', 'contact'];

        // Ensure they are arrays
        $enabledSections = is_array($enabledSections) ? $enabledSections : ['hero', 'services', 'team', 'contact'];
        $sectionOrder = is_array($sectionOrder) ? $sectionOrder : ['hero', 'about', 'services', 'team', 'gallery', 'testimonials', 'contact'];

        // Filter section order to only include enabled sections
        $orderedSections = array_filter($sectionOrder, function($section) use ($enabledSections) {
            return in_array($section, $enabledSections);
        });

        return [
            'enabled' => $enabledSections,
            'order' => array_values($orderedSections),
        ];
    }

    /**
     * Resolve navigation structure
     */
    protected function resolveNavigation(?WebsiteConfiguration $config): array
    {
        $sections = $this->resolveSections($config);
        
        // Build navigation from section order
        $navigation = [];
        foreach ($sections['order'] as $section) {
            $navigation[] = [
                'id' => $section,
                'label' => $this->getSectionLabel($section),
                'anchor' => '#' . $section,
            ];
        }

        return [
            'items' => $navigation,
            'show_booking_cta' => in_array('services', $sections['enabled']),
        ];
    }

    /**
     * Get human-readable label for section
     */
    protected function getSectionLabel(string $section): string
    {
        return match($section) {
            'hero' => 'Home',
            'about' => 'About',
            'services' => 'Services',
            'team' => 'Our Team',
            'gallery' => 'Gallery',
            'testimonials' => 'Reviews',
            'contact' => 'Contact',
            default => ucfirst($section),
        };
    }
}
