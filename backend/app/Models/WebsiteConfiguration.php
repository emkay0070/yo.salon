<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WebsiteConfiguration extends Model
{
    use HasUuids;

    protected $fillable = [
        'configurable_type',
        'configurable_id',
        'enabled_sections',
        'section_order',
        'theme',
        'custom_colors',
        'custom_fonts',
        'is_active',
    ];

    protected $casts = [
        'enabled_sections' => 'array',
        'section_order' => 'array',
        'custom_colors' => 'array',
        'custom_fonts' => 'array',
        'is_active' => 'boolean',
    ];

    public function configurable(): MorphTo
    {
        return $this->morphTo();
    }

    // Available sections
    public static function getAvailableSections(): array
    {
        return [
            'hero' => 'Hero Section',
            'services' => 'Services',
            'team' => 'Team/Staff',
            'gallery' => 'Gallery',
            'testimonials' => 'Testimonials',
            'products' => 'Products',
            'faqs' => 'FAQs',
            'contact' => 'Contact',
            'book_now' => 'Book Now CTA',
            'careers' => 'Careers',
        ];
    }

    // Available themes
    public static function getAvailableThemes(): array
    {
        return [
            'luxury_noir' => 'Luxury Noir',
            'minimal_studio' => 'Minimal Studio',
            'modern_glass' => 'Modern Glass',
            'urban_edge' => 'Urban Edge',
            'natural_spa' => 'Natural Spa',
            'classic_beauty' => 'Classic Beauty',
        ];
    }

    // Get theme configuration
    public function getThemeConfig(): array
    {
        $themes = [
            'luxury_noir' => [
                'primary' => '#FFD700',
                'secondary' => '#C9A227',
                'background' => '#050505',
                'surface' => '#0a0a0a',
                'text' => '#ffffff',
                'border_radius' => '16px',
                'font_heading' => 'Sora',
                'font_body' => 'Inter',
            ],
            'minimal_studio' => [
                'primary' => '#2563eb',
                'secondary' => '#1d4ed8',
                'background' => '#ffffff',
                'surface' => '#f8fafc',
                'text' => '#0f172a',
                'border_radius' => '8px',
                'font_heading' => 'Inter',
                'font_body' => 'Inter',
            ],
            'modern_glass' => [
                'primary' => '#06b6d4',
                'secondary' => '#0891b2',
                'background' => '#0f172a',
                'surface' => 'rgba(255,255,255,0.05)',
                'text' => '#e2e8f0',
                'border_radius' => '12px',
                'font_heading' => 'Space Grotesk',
                'font_body' => 'Inter',
            ],
            'urban_edge' => [
                'primary' => '#f97316',
                'secondary' => '#ea580c',
                'background' => '#18181b',
                'surface' => '#27272a',
                'text' => '#fafafa',
                'border_radius' => '0px',
                'font_heading' => 'Oswald',
                'font_body' => 'Roboto',
            ],
            'natural_spa' => [
                'primary' => '#84cc16',
                'secondary' => '#65a30d',
                'background' => '#f0fdf4',
                'surface' => '#dcfce7',
                'text' => '#14532d',
                'border_radius' => '20px',
                'font_heading' => 'Playfair Display',
                'font_body' => 'Lato',
            ],
            'classic_beauty' => [
                'primary' => '#ec4899',
                'secondary' => '#db2777',
                'background' => '#fdf2f8',
                'surface' => '#fce7f3',
                'text' => '#831843',
                'border_radius' => '24px',
                'font_heading' => 'Playfair Display',
                'font_body' => 'Georgia',
            ],
        ];

        return $themes[$this->theme] ?? $themes['luxury_noir'];
    }

    // Merge custom colors with theme
    public function getFinalColors(): array
    {
        $themeConfig = $this->getThemeConfig();
        if ($this->custom_colors) {
            return array_merge($themeConfig, $this->custom_colors);
        }
        return $themeConfig;
    }
}
