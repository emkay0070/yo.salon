<?php

namespace App\Services;

use App\Models\WebsiteConfiguration;
use App\Models\Provider;
use App\Models\Location;

class WebsiteConfigurationResolver
{
    /**
     * Resolve the effective website configuration for a provider (and optional location)
     */
    public function resolve(Provider $provider, ?Location $location = null): array
    {
        $config = $this->getConfiguration($provider);
        
        return [
            'salon' => $this->resolveContextInfo($provider, $location),
            'brand' => $this->resolveBranding($provider, $config),
            'theme' => $this->resolveTheme($config),
            'sections' => $this->resolveSections($config),
            'navigation' => $this->resolveNavigation($config),
        ];
    }

    /**
     * Get the website configuration (custom or default)
     */
    protected function getConfiguration(Provider $provider): ?WebsiteConfiguration
    {
        return WebsiteConfiguration::where('configurable_type', Provider::class)
            ->where('configurable_id', $provider->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Resolve provider and location information
     */
    protected function resolveContextInfo(Provider $provider, ?Location $location): array
    {
        $address = $location?->address;
        $city = $location?->city;

        return [
            'id' => $location?->id ?? $provider->id, // Frontend uses this ID for booking context
            'name' => $location?->name ?? $provider->display_name,
            'slug' => $location?->slug ?? $provider->slug,
            'description' => $location?->description ?? $provider->description,
            'logo' => $provider->logo_url,
            'phone' => $location?->phone ?? $provider->phone,
            'email' => $location?->email ?? $provider->email,
            'address' => $address,
            'city' => $city,
            'whatsapp' => $location?->whatsapp,
            'lat' => $location?->lat,
            'lng' => $location?->lng,
            'opening_hours' => [], // Should be resolved via AvailabilityEngine/ScheduleResolver in API response if needed
        ];
    }

    /**
     * Resolve branding information
     */
    protected function resolveBranding(Provider $provider, ?WebsiteConfiguration $config): array
    {
        $brandExperience = $provider->brandExperience;

        // Use custom colors from config if available, otherwise fall back to brand experience
        $customColors = $config ? json_decode($config->custom_colors, true) : null;
        $customFonts = $config ? json_decode($config->custom_fonts, true) : null;

        return [
            'logo' => $config?->logo ?? $provider->logo_url,
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
